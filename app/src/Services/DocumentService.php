<?php

declare(strict_types=1);

namespace Services;

use Domain\Document;
use Domain\DocumentException;
use Domain\DocumentStatus;
use Domain\Session;
use Domain\PdfTextExtractor;
use Domain\PlainTextExtractor;
use Domain\TextExtractorInterface;
use Models\ConversationRepository;
use Models\DocumentRepository;
use Models\EnrollmentRepository;
use Models\SessionRepository;
use PDO;
use Throwable;

/**
 * File management for session documents (phase 1 of the documents/RAG feature):
 * validate, store on disk (outside the web root), record metadata, and
 * best-effort extract the text for later phases. Access is gated here: a
 * session document is readable by its owner teacher and the enrolled students.
 */
class DocumentService
{
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 Mo
    private const MAX_PER_SESSION = 20;
    private const MAX_PER_CONVERSATION = 10;

    /** Upper bound on the characters injected into the model's system prompt. */
    public const MAX_INJECTED_CHARS = 12000;

    /** Real (finfo) MIME type → stored file extension. */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'text/plain'      => 'txt',
        'text/markdown'   => 'md',
    ];

    private DocumentRepository $documents;
    private SessionRepository $sessions;
    private EnrollmentRepository $enrollments;
    private ConversationRepository $conversations;
    /** @var list<TextExtractorInterface> */
    private array $extractors;
    private string $storageDir;

    public function __construct(PDO $pdo)
    {
        $this->documents     = new DocumentRepository($pdo);
        $this->sessions      = new SessionRepository($pdo);
        $this->enrollments   = new EnrollmentRepository($pdo);
        $this->conversations = new ConversationRepository($pdo);
        $this->extractors    = [new PlainTextExtractor(), new PdfTextExtractor()];
        $this->storageDir    = dirname(__DIR__, 2) . '/storage/documents';
    }

    /**
     * Validates and stores an uploaded file as a session document, then
     * best-effort extracts its text (a failure only sets status FAILED, the
     * file stays downloadable).
     *
     * @param array<string, mixed> $file a single $_FILES entry
     */
    public function attachToSession(int $sessionId, int $userId, array $file): Document
    {
        $size = $this->assertUploadOk($file);
        if ($this->documents->countBySession($sessionId) >= self::MAX_PER_SESSION) {
            throw DocumentException::quotaReached(self::MAX_PER_SESSION);
        }

        return $this->moveAndRecord($file, $size, $userId, $sessionId, null);
    }

    /**
     * Validates and stores an uploaded file as a CONVERSATION document (phase 2),
     * then best-effort extracts its text. The caller must own the conversation;
     * importing is refused during an EXAM session.
     *
     * @param array<string, mixed> $file a single $_FILES entry
     */
    public function attachToConversation(int $conversationId, int $userId, array $file): Document
    {
        $session = $this->assertCanImportToConversation($conversationId, $userId);

        // Per-session limits override the global ones for a session-bound chat.
        $maxBytes    = self::MAX_BYTES;
        $allowedExts = null; // null = every globally-supported type
        if ($session !== null) {
            if (!$session->documentsEnabled()) {
                throw DocumentException::documentsDisabled();
            }
            if ($session->documentsMaxBytes() !== null) {
                $maxBytes = min($session->documentsMaxBytes(), self::MAX_BYTES);
            }
            $types = $session->documentsAllowedTypesList();
            if ($types !== []) {
                $allowedExts = $types;
            }
        }

        $size = $this->assertUploadOk($file, $maxBytes);
        if ($this->documents->countByConversation($conversationId) >= self::MAX_PER_CONVERSATION) {
            throw DocumentException::quotaReachedConversation(self::MAX_PER_CONVERSATION);
        }

        return $this->moveAndRecord($file, $size, $userId, null, $conversationId, $allowedExts);
    }

    /**
     * @return list<Document>
     */
    public function listForSession(int $sessionId): array
    {
        return array_map(
            static fn (array $r): Document => Document::fromRow($r),
            $this->documents->listBySession($sessionId)
        );
    }

    /**
     * Resolves a session document for download after checking the caller may
     * read it (session owner OR enrolled student). The document must belong to
     * the session named in the URL, so a doc id cannot be fetched through
     * another session's path.
     *
     * @return array{path: string, name: string, mime: string}
     */
    public function openForDownload(int $sessionId, int $documentId, int $userId): array
    {
        $doc = $this->loadOr404($documentId);
        if ($doc->sessionId() !== $sessionId || !$this->canAccessSession($sessionId, $userId)) {
            throw DocumentException::forbidden();
        }
        $absolute = $this->storageDir . '/' . $doc->storedPath();
        if (!is_file($absolute)) {
            throw DocumentException::notFound();
        }

        return ['path' => $absolute, 'name' => $doc->originalName(), 'mime' => $doc->mimeType()];
    }

    /**
     * Deletes a session document the given teacher owns (the session owner).
     * Returns the session id so the controller can redirect back.
     */
    public function deleteFromSession(int $documentId, int $userId): int
    {
        $doc       = $this->loadOr404($documentId);
        $sessionId = $doc->sessionId();
        if ($sessionId === null || !$this->isOwner($sessionId, $userId)) {
            throw DocumentException::forbidden();
        }
        $absolute = $this->storageDir . '/' . $doc->storedPath();
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $this->documents->delete($documentId);

        return $sessionId;
    }

    // ----------------------------------------------------------------
    // Conversation documents (phase 2)
    // ----------------------------------------------------------------

    /**
     * Pending documents (not yet sent with a message) of a conversation the
     * caller owns — i.e. what the chat composer shows as removable chips.
     *
     * @return list<Document>
     */
    public function listForConversation(int $conversationId, int $userId): array
    {
        if ($this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId) === null) {
            throw DocumentException::forbidden();
        }

        return array_map(
            static fn (array $r): Document => Document::fromRow($r),
            $this->documents->listPendingByConversation($conversationId)
        );
    }

    /**
     * Ties a conversation's still-pending documents to the message just sent, so
     * each document is recorded under the message it was sent with (provenance).
     * The documents stay readable/injected for the whole conversation.
     */
    public function bindPendingToInteraction(int $conversationId, int $interactionId): void
    {
        $this->documents->bindPendingToInteraction($conversationId, $interactionId);
    }

    /**
     * Documents tied to a single message. No access check: the caller already
     * resolved the conversation/interaction as its own.
     *
     * @return list<Document>
     */
    public function documentsForInteraction(int $interactionId): array
    {
        return array_map(
            static fn (array $r): Document => Document::fromRow($r),
            $this->documents->listByInteraction($interactionId)
        );
    }

    /**
     * Documents already sent, grouped by the message (interaction) they belong
     * to — used to render them under their message in the conversation history.
     * No access check: the caller already resolved the conversation as its own.
     *
     * @return array<int, list<Document>>
     */
    public function documentsByInteractionForConversation(int $conversationId): array
    {
        $map = [];
        foreach ($this->documents->listBoundByConversation($conversationId) as $row) {
            $doc = Document::fromRow($row);
            $iid = (int) $row['interaction_id'];
            $map[$iid][] = $doc;
        }

        return $map;
    }

    /**
     * Resolves a conversation document for download after checking the caller
     * owns the conversation and the document belongs to it.
     *
     * @return array{path: string, name: string, mime: string}
     */
    public function openConversationDownload(int $conversationId, int $documentId, int $userId): array
    {
        if ($this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId) === null) {
            throw DocumentException::forbidden();
        }
        $doc = $this->loadOr404($documentId);
        if ($doc->conversationId() !== $conversationId) {
            throw DocumentException::forbidden();
        }
        $absolute = $this->storageDir . '/' . $doc->storedPath();
        if (!is_file($absolute)) {
            throw DocumentException::notFound();
        }

        return ['path' => $absolute, 'name' => $doc->originalName(), 'mime' => $doc->mimeType()];
    }

    /**
     * Deletes a conversation document owned by the caller. Returns the
     * conversation id so the caller can refresh.
     */
    public function deleteFromConversation(int $documentId, int $userId): int
    {
        $doc            = $this->loadOr404($documentId);
        $conversationId = $doc->conversationId();
        if (
            $conversationId === null
            || $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId) === null
        ) {
            throw DocumentException::forbidden();
        }
        $absolute = $this->storageDir . '/' . $doc->storedPath();
        if (is_file($absolute)) {
            @unlink($absolute);
        }
        $this->documents->delete($documentId);

        return $conversationId;
    }

    /**
     * Builds the "documents provided by the user" block injected into the model's
     * system prompt (phase 2). Concatenates the READY documents' extracted text,
     * capped at MAX_INJECTED_CHARS overall; returns '' when there is nothing to
     * inject. Caller-agnostic: it does not read the session pre-prompt.
     */
    public function buildSystemContext(int $conversationId): string
    {
        $rows = $this->documents->listByConversation($conversationId);
        if ($rows === []) {
            return '';
        }

        $budget = self::MAX_INJECTED_CHARS;
        $parts  = [];
        // Oldest first so the earliest documents are the ones kept under the cap.
        foreach (array_reverse($rows) as $row) {
            if ($budget <= 0) {
                break;
            }
            $doc  = Document::fromRow($row);
            $text = $doc->extractedText();
            if ($doc->status() !== DocumentStatus::Ready || $text === null || trim($text) === '') {
                continue;
            }
            $truncated = false;
            if (mb_strlen($text) > $budget) {
                $text      = mb_substr($text, 0, $budget);
                $truncated = true;
            }
            $budget -= mb_strlen($text);
            $parts[] = '--- ' . $doc->originalName() . " ---\n" . $text
                . ($truncated ? "\n(document tronqué)" : '');
        }

        if ($parts === []) {
            return '';
        }

        return "Documents fournis par l'utilisateur (extraits) :\n" . implode("\n\n", $parts);
    }

    /**
     * Document settings for a conversation's chat UI: whether the paperclip is
     * available, and which extensions the file picker should accept. Free chats
     * (no session) and any non-restricting session fall back to the global set.
     *
     * @return array{enabled: bool, acceptExts: list<string>}
     */
    public function sessionDocumentsUiConfig(?int $sessionId): array
    {
        $default = ['enabled' => true, 'acceptExts' => ['pdf', 'md', 'txt']];
        if ($sessionId === null) {
            return $default;
        }
        $row = $this->sessions->findById($sessionId);
        if ($row === null) {
            return $default;
        }
        $session = Session::fromRow($row);
        $types   = $session->documentsAllowedTypesList();

        return [
            'enabled'    => $session->documentsEnabled() && $session->type()->value !== 'EXAM',
            'acceptExts' => $types === [] ? ['pdf', 'md', 'txt'] : $types,
        ];
    }

    // ----------------------------------------------------------------
    // internals
    // ----------------------------------------------------------------

    /**
     * Shared upload guards (presence, PHP upload error, real upload, size).
     * Returns the byte size. The per-scope quota is checked by the caller.
     *
     * @param array<string, mixed> $file
     */
    private function assertUploadOk(array $file, int $maxBytes = self::MAX_BYTES): int
    {
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE || ($file['tmp_name'] ?? '') === '') {
            throw DocumentException::noFile();
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw DocumentException::tooLarge((int) (self::MAX_BYTES / 1024 / 1024));
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw DocumentException::uploadFailed();
        }
        if (!is_uploaded_file((string) $file['tmp_name'])) {
            throw DocumentException::uploadFailed();
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw DocumentException::noFile();
        }
        if ($size > $maxBytes) {
            throw DocumentException::tooLarge((int) ($maxBytes / 1024 / 1024));
        }

        return $size;
    }

    /**
     * Moves a validated upload under its scope folder (session_<id> or
     * conversation_<id>), records the row, then best-effort extracts the text.
     * Exactly one of $sessionId / $conversationId is non-null (DB scope check).
     *
     * @param array<string, mixed> $file
     * @param list<string>|null $allowedExts per-session type restriction (stored extensions)
     */
    private function moveAndRecord(array $file, int $size, int $userId, ?int $sessionId, ?int $conversationId, ?array $allowedExts = null): Document
    {
        $tmp   = (string) $file['tmp_name'];
        $scope = $sessionId !== null ? 'session_' . $sessionId : 'conversation_' . $conversationId;

        $originalName = $this->sanitizeName((string) ($file['name'] ?? 'document'));
        [$mime, $ext] = $this->resolveMimeAndExtension($tmp, $originalName, $allowedExts);

        $dir = $this->storageDir . '/' . $scope;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw DocumentException::uploadFailed();
        }
        $stored   = $scope . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $absolute = $this->storageDir . '/' . $stored;
        if (!move_uploaded_file($tmp, $absolute)) {
            throw DocumentException::uploadFailed();
        }

        $row = $this->documents->insert([
            'session_id'      => $sessionId,
            'conversation_id' => $conversationId,
            'uploaded_by_id'  => $userId,
            'original_name'   => $originalName,
            'stored_path'     => $stored,
            'mime_type'       => $mime,
            'size_bytes'      => $size,
            'status'          => DocumentStatus::Pending->value,
        ]);

        try {
            $text = $this->extractText($absolute, $mime);
            $this->documents->updateExtraction((int) $row['id'], $text, DocumentStatus::Ready->value);
            $row['extracted_text'] = $text;
            $row['status']         = DocumentStatus::Ready->value;
        } catch (Throwable) {
            $this->documents->updateExtraction((int) $row['id'], null, DocumentStatus::Failed->value);
            $row['status'] = DocumentStatus::Failed->value;
        }

        return Document::fromRow($row);
    }

    /**
     * The caller may import into the conversation only if they own it; and the
     * import is refused when the conversation belongs to an EXAM session.
     */
    private function assertCanImportToConversation(int $conversationId, int $userId): ?Session
    {
        $conv = $this->conversations->getConversationByUserIdAndConversationId($userId, $conversationId);
        if ($conv === null) {
            throw DocumentException::forbidden();
        }
        $sessionId = isset($conv['session_id']) && $conv['session_id'] !== null ? (int) $conv['session_id'] : null;
        if ($sessionId === null) {
            return null;
        }
        $row = $this->sessions->findById($sessionId);
        if ($row === null) {
            return null;
        }
        $session = Session::fromRow($row);
        if ($session->type()->value === 'EXAM') {
            throw DocumentException::examImportDisabled();
        }

        return $session;
    }

    private function loadOr404(int $documentId): Document
    {
        $row = $this->documents->findById($documentId);
        if ($row === null) {
            throw DocumentException::notFound();
        }

        return Document::fromRow($row);
    }

    private function canAccessSession(int $sessionId, int $userId): bool
    {
        return $this->isOwner($sessionId, $userId)
            || $this->enrollments->exists($userId, $sessionId);
    }

    private function isOwner(int $sessionId, int $userId): bool
    {
        $row = $this->sessions->findById($sessionId);

        return $row !== null
            && isset($row['teacher_id'])
            && $row['teacher_id'] !== null
            && (int) $row['teacher_id'] === $userId;
    }

    private function extractText(string $absolutePath, string $mime): string
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($mime)) {
                return $extractor->extract($absolutePath);
            }
        }
        throw DocumentException::extractionFailed();
    }

    /**
     * Real MIME (via finfo, not the client) + the extension to store under.
     * Markdown is reported as text/plain by finfo, so it is recovered by
     * extension.
     *
     * When $allowedExts is given (per-session restriction), the resolved
     * extension must also belong to it — a subset of the global allowed types.
     *
     * @param list<string>|null $allowedExts
     * @return array{0: string, 1: string} [mimeType, storedExtension]
     */
    private function resolveMimeAndExtension(string $tmp, string $originalName, ?array $allowedExts = null): array
    {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo !== false ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($detected === 'text/plain' && in_array($ext, ['md', 'markdown'], true)) {
            $resolved = ['text/markdown', 'md'];
        } elseif (isset(self::ALLOWED[$detected])) {
            $resolved = [$detected, self::ALLOWED[$detected]];
        } else {
            throw DocumentException::unsupportedType();
        }

        if ($allowedExts !== null && !in_array($resolved[1], $allowedExts, true)) {
            throw DocumentException::typeNotAllowed();
        }

        return $resolved;
    }

    private function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\/\\\\]+/', '_', $name) ?? 'document';
        $name = trim($name);

        return $name === '' ? 'document' : mb_substr($name, 0, 255);
    }
}
