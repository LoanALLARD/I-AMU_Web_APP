<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Domain\DocumentException;
use Services\DocumentService;
use Services\SessionService;

/**
 * HTTP entry points for session documents (phase 1). Upload/delete are reserved
 * to the session owner ; download is open to the owner and the enrolled
 * students (the access check lives in DocumentService).
 */
class DocumentController extends Controller
{
    private DocumentService $documents;
    private SessionService $sessions;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->documents = new DocumentService($pdo);
        $this->sessions  = new SessionService($pdo);
    }

    /** POST /sessions/{id}/documents — the owner attaches one or more documents. */
    public function uploadToSession(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->requireOwnedSession($sessionId);

        $user  = $this->currentUser();
        $files = $this->normalizeUploads($_FILES['document'] ?? []);

        if ($files === []) {
            $this->flash('error', 'Aucun fichier sélectionné.');
            $this->redirect('/sessions/' . $sessionId);
        }

        $added = 0;
        foreach ($files as $file) {
            try {
                $this->documents->attachToSession($sessionId, (int) ($user['id'] ?? 0), $file);
                $added++;
            } catch (DocumentException $e) {
                $this->flash('error', ((string) ($file['name'] ?? 'fichier')) . ' : ' . $e->getMessage());
            }
        }

        if ($added > 0) {
            $this->flash('success', $added . ' document(s) ajouté(s) à la session.');
        }

        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * Flattens PHP's `$_FILES['document']` into a list of single-file arrays,
     * supporting both the single (`name="document"`) and multiple
     * (`name="document[]"`) form shapes, and skipping empty slots.
     *
     * @param array<string, mixed> $field
     * @return list<array<string, mixed>>
     */
    private function normalizeUploads(array $field): array
    {
        if (!isset($field['name'])) {
            return [];
        }

        // Single-file form: name/tmp_name/... are scalars, not arrays.
        if (!is_array($field['name'])) {
            return ((int) ($field['error'] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE
                ? []
                : [$field];
        }

        $files = [];
        foreach (array_keys($field['name']) as $i) {
            if (((int) ($field['error'][$i] ?? UPLOAD_ERR_NO_FILE)) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => $field['name'][$i],
                'type'     => $field['type'][$i] ?? '',
                'tmp_name' => $field['tmp_name'][$i] ?? '',
                'error'    => $field['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $field['size'][$i] ?? 0,
            ];
        }

        return $files;
    }

    /**
     * GET /documents/session_{sessionId}/{docId} — stream a session document
     * to an authorised viewer (owner or enrolled student).
     */
    public function download(string $sessionId, string $docId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        try {
            $file = $this->documents->openForDownload((int) $sessionId, (int) $docId, (int) ($user['id'] ?? 0));
        } catch (DocumentException) {
            $this->renderForbidden();
        }

        header('Content-Type: ' . $file['mime']);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $file['name']) . '"');
        header('Content-Length: ' . (string) (filesize($file['path']) ?: 0));
        header('X-Content-Type-Options: nosniff');
        readfile($file['path']);
        exit;
    }

    /** POST /documents/{id}/delete — the owner removes a session document. */
    public function delete(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        try {
            $sessionId = $this->documents->deleteFromSession((int) $id, (int) ($user['id'] ?? 0));
            $this->flash('success', 'Document supprimé.');
            $this->redirect('/sessions/' . $sessionId);
        } catch (DocumentException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/sessions');
        }
    }

    private function requireOwnedSession(int $sessionId): void
    {
        $session = $this->sessions->find($sessionId);
        $user    = $this->currentUser();
        if (
            $session === null
            || $user === null
            || $session->teacherId() === null
            || (int) $user['id'] !== $session->teacherId()
        ) {
            $this->renderForbidden();
        }
    }
}
