<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Core\Csrf;
use Data\Database;
use Domain\Document;
use Domain\DocumentException;
use Models\AiRepository;
use Models\ConversationRepository;
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

    // ----------------------------------------------------------------
    // Conversation documents (phase 2) — JSON, called from the chat composer.
    // ----------------------------------------------------------------

    /**
     * POST /chat/documents — attaches one or more documents to the caller's
     * conversation. When the chat is brand new (no id yet) the conversation is
     * created from the selected model first, so a user can drop a file before
     * sending the first message. Answers JSON.
     */
    public function uploadToConversation(): void
    {
        $this->requireAuth();
        if (!Csrf::verify((string) ($_POST['_csrf_token'] ?? ''))) {
            $this->json(['error' => 'Session expirée, merci de recharger la page.'], 419);
        }

        $user   = $this->currentUser();
        $userId = (int) ($user['id'] ?? 0);
        $pdo    = Database::getConnection();

        $conversationId      = (int) ($_POST['conversation_id'] ?? 0);
        $createdConversation = null;
        if ($conversationId <= 0) {
            $model = (new AiRepository($pdo))->getModelByName((string) ($_POST['model'] ?? ''));
            if ($model === null) {
                $this->json(['error' => 'Sélectionnez un modèle avant de joindre un document.'], 400);
            }
            $conv = (new ConversationRepository($pdo))->newConversation(
                $userId,
                (int) $model['id'],
                null,
                'Nouvelle conversation'
            );
            if ($conv === null) {
                $this->json(['error' => 'Impossible de créer la conversation.'], 500);
            }
            $conversationId      = (int) $conv['id'];
            $createdConversation = ['id' => $conversationId, 'name' => (string) ($conv['name'] ?? 'Nouvelle conversation')];
        }

        $files = $this->normalizeUploads($_FILES['document'] ?? []);
        if ($files === []) {
            $this->json(['error' => 'Aucun fichier reçu.'], 400);
        }

        $saved  = [];
        $errors = [];
        foreach ($files as $file) {
            try {
                $doc     = $this->documents->attachToConversation($conversationId, $userId, $file);
                $saved[] = $this->documentPayload($doc);
            } catch (DocumentException $e) {
                $errors[] = ((string) ($file['name'] ?? 'fichier')) . ' : ' . $e->getMessage();
            }
        }

        if ($saved === [] && $errors !== []) {
            $this->json(['error' => implode(' ', $errors), 'conversation_id' => $conversationId], 422);
        }

        $this->json([
            'conversation_id' => $conversationId,
            'conversation'    => $createdConversation,
            'documents'       => $saved,
            'errors'          => $errors,
        ]);
    }

    /** GET /chat/documents/{conversationId} — JSON list of attached documents. */
    public function conversationDocuments(string $conversationId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        try {
            $docs = $this->documents->listForConversation((int) $conversationId, (int) ($user['id'] ?? 0));
        } catch (DocumentException $e) {
            $this->json(['error' => $e->getMessage()], 403);
        }

        $this->json(['documents' => array_map([$this, 'documentPayload'], $docs)]);
    }

    /** GET /documents/conversation_{conversationId}/{docId} — stream a chat document to its owner. */
    public function downloadFromConversation(string $conversationId, string $docId): void
    {
        $this->requireAuth();
        $user = $this->currentUser();
        try {
            $file = $this->documents->openConversationDownload((int) $conversationId, (int) $docId, (int) ($user['id'] ?? 0));
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

    /** POST /chat/documents/{id}/delete — the owner removes a chat document. */
    public function deleteFromConversation(string $id): void
    {
        $this->requireAuth();
        if (!Csrf::verify((string) ($_POST['_csrf_token'] ?? ''))) {
            $this->json(['error' => 'Session expirée, merci de recharger la page.'], 419);
        }
        $user = $this->currentUser();
        try {
            $conversationId = $this->documents->deleteFromConversation((int) $id, (int) ($user['id'] ?? 0));
        } catch (DocumentException $e) {
            $this->json(['error' => $e->getMessage()], 403);
        }

        $this->json(['ok' => true, 'conversation_id' => $conversationId]);
    }

    /**
     * Compact JSON view of a document for the chat UI.
     *
     * @return array{id:int|null, name:string, size:string, kind:string, status:string}
     */
    private function documentPayload(Document $doc): array
    {
        return [
            'id'     => $doc->id(),
            'name'   => $doc->originalName(),
            'size'   => $doc->humanSize(),
            'kind'   => $doc->kindLabel(),
            'status' => $doc->status()->value,
        ];
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
