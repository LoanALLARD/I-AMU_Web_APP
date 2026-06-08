<?php

declare(strict_types=1);

namespace Services;

use Domain\Document;
use Domain\DocumentException;
use Domain\DocumentStatus;
use Domain\PdfTextExtractor;
use Domain\PlainTextExtractor;
use Domain\TextExtractorInterface;
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

    /** Real (finfo) MIME type → stored file extension. */
    private const ALLOWED = [
        'application/pdf' => 'pdf',
        'text/plain'      => 'txt',
        'text/markdown'   => 'md',
    ];

    private DocumentRepository $documents;
    private SessionRepository $sessions;
    private EnrollmentRepository $enrollments;
    /** @var list<TextExtractorInterface> */
    private array $extractors;
    private string $storageDir;

    public function __construct(PDO $pdo)
    {
        $this->documents   = new DocumentRepository($pdo);
        $this->sessions    = new SessionRepository($pdo);
        $this->enrollments = new EnrollmentRepository($pdo);
        $this->extractors  = [new PlainTextExtractor(), new PdfTextExtractor()];
        $this->storageDir  = dirname(__DIR__, 2) . '/storage/documents';
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

        $tmp = (string) $file['tmp_name'];
        if (!is_uploaded_file($tmp)) {
            throw DocumentException::uploadFailed();
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw DocumentException::noFile();
        }
        if ($size > self::MAX_BYTES) {
            throw DocumentException::tooLarge((int) (self::MAX_BYTES / 1024 / 1024));
        }
        if ($this->documents->countBySession($sessionId) >= self::MAX_PER_SESSION) {
            throw DocumentException::quotaReached(self::MAX_PER_SESSION);
        }

        $originalName = $this->sanitizeName((string) ($file['name'] ?? 'document'));
        [$mime, $ext] = $this->resolveMimeAndExtension($tmp, $originalName);

        $dir = $this->storageDir . '/session_' . $sessionId;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw DocumentException::uploadFailed();
        }
        $stored   = 'session_' . $sessionId . '/' . bin2hex(random_bytes(16)) . '.' . $ext;
        $absolute = $this->storageDir . '/' . $stored;
        if (!move_uploaded_file($tmp, $absolute)) {
            throw DocumentException::uploadFailed();
        }

        $row = $this->documents->insert([
            'session_id'      => $sessionId,
            'conversation_id' => null,
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
    // internals
    // ----------------------------------------------------------------

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
     * @return array{0: string, 1: string} [mimeType, storedExtension]
     */
    private function resolveMimeAndExtension(string $tmp, string $originalName): array
    {
        $finfo    = finfo_open(FILEINFO_MIME_TYPE);
        $detected = $finfo !== false ? (finfo_file($finfo, $tmp) ?: '') : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($detected === 'text/plain' && in_array($ext, ['md', 'markdown'], true)) {
            return ['text/markdown', 'md'];
        }
        if (isset(self::ALLOWED[$detected])) {
            return [$detected, self::ALLOWED[$detected]];
        }

        throw DocumentException::unsupportedType();
    }

    private function sanitizeName(string $name): string
    {
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\/\\\\]+/', '_', $name) ?? 'document';
        $name = trim($name);

        return $name === '' ? 'document' : mb_substr($name, 0, 255);
    }
}
