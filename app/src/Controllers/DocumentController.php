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

    /** POST /sessions/{id}/documents - the owner attaches a document. */
    public function uploadToSession(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->requireOwnedSession($sessionId);

        $user = $this->currentUser();
        try {
            /** @var array<string, mixed> $file */
            $file = $_FILES['document'] ?? [];
            $this->documents->attachToSession($sessionId, (int) ($user['id'] ?? 0), $file);
            $this->flash('success', 'Document ajouté à la session.');
        } catch (DocumentException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * GET /documents/session_{sessionId}/{docId} - stream a session document
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

    /** POST /documents/{id}/delete - the owner removes a session document. */
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
