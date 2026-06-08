<?php

declare(strict_types=1);

namespace Domain;

use DateTimeImmutable;

/**
 * A file attached either to a session (pedagogical document, shared with the
 * enrolled students) or to a conversation (private chat context). Built from a
 * DB row via {@see fromRow()}; the Model layer only handles arrays.
 *
 * Scope invariant (enforced by the DB `ck_documents_scope`): exactly one of
 * sessionId / conversationId is set.
 */
final class Document
{
    public function __construct(
        private readonly ?int $id,
        private readonly ?int $sessionId,
        private readonly ?int $conversationId,
        private readonly int $uploadedById,
        private readonly string $originalName,
        private readonly string $storedPath,
        private readonly string $mimeType,
        private readonly int $sizeBytes,
        private readonly ?string $extractedText,
        private readonly DocumentStatus $status,
        private readonly ?DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $opt = static fn (string $key): ?int =>
            isset($row[$key]) && $row[$key] !== null ? (int) $row[$key] : null;

        return new self(
            id:             $opt('id'),
            sessionId:      $opt('session_id'),
            conversationId: $opt('conversation_id'),
            uploadedById:   (int) $row['uploaded_by_id'],
            originalName:   (string) $row['original_name'],
            storedPath:     (string) $row['stored_path'],
            mimeType:       (string) $row['mime_type'],
            sizeBytes:      (int) $row['size_bytes'],
            extractedText:  isset($row['extracted_text']) && $row['extracted_text'] !== null
                ? (string) $row['extracted_text']
                : null,
            status:         DocumentStatus::from((string) $row['status']),
            createdAt:      isset($row['created_at']) && $row['created_at'] !== null
                ? new DateTimeImmutable((string) $row['created_at'])
                : null,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function sessionId(): ?int
    {
        return $this->sessionId;
    }

    public function conversationId(): ?int
    {
        return $this->conversationId;
    }

    public function uploadedById(): int
    {
        return $this->uploadedById;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function storedPath(): string
    {
        return $this->storedPath;
    }

    public function mimeType(): string
    {
        return $this->mimeType;
    }

    public function sizeBytes(): int
    {
        return $this->sizeBytes;
    }

    public function extractedText(): ?string
    {
        return $this->extractedText;
    }

    public function status(): DocumentStatus
    {
        return $this->status;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Human-readable file size (e.g. "1,2 Mo"). */
    public function humanSize(): string
    {
        if ($this->sizeBytes < 1024) {
            return $this->sizeBytes . ' o';
        }
        if ($this->sizeBytes < 1024 * 1024) {
            return number_format($this->sizeBytes / 1024, 0, ',', ' ') . ' Ko';
        }
        return number_format($this->sizeBytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
    }

    /** Short kind label derived from the MIME type. */
    public function kindLabel(): string
    {
        return match ($this->mimeType) {
            'application/pdf' => 'PDF',
            'text/markdown'   => 'Markdown',
            'text/plain'      => 'Texte',
            default           => 'Fichier',
        };
    }
}
