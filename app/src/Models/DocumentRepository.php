<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for the `documents` table. Returns raw rows (the Service layer
 * hydrates Domain\Document).
 */
class DocumentRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @param array<string, scalar|null> $data
     * @return array<string, mixed>
     */
    public function insert(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO documents
                (session_id, conversation_id, uploaded_by_id, original_name,
                 stored_path, mime_type, size_bytes, status)
             VALUES
                (:session_id, :conversation_id, :uploaded_by_id, :original_name,
                 :stored_path, :mime_type, :size_bytes, :status)
             RETURNING *'
        );
        $stmt->execute([
            'session_id'      => $data['session_id'],
            'conversation_id' => $data['conversation_id'],
            'uploaded_by_id'  => $data['uploaded_by_id'],
            'original_name'   => $data['original_name'],
            'stored_path'     => $data['stored_path'],
            'mime_type'       => $data['mime_type'],
            'size_bytes'      => $data['size_bytes'],
            'status'          => $data['status'],
        ]);

        /** @var array<string, mixed> $row */
        $row = $stmt->fetch();

        return $row;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listBySession(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documents WHERE session_id = :sid ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    public function countBySession(int $sessionId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE session_id = :sid');
        $stmt->execute(['sid' => $sessionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listByConversation(int $conversationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM documents WHERE conversation_id = :cid ORDER BY created_at DESC, id DESC'
        );
        $stmt->execute(['cid' => $conversationId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    public function countByConversation(int $conversationId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM documents WHERE conversation_id = :cid');
        $stmt->execute(['cid' => $conversationId]);

        return (int) $stmt->fetchColumn();
    }

    public function updateExtraction(int $id, ?string $text, string $status): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE documents SET extracted_text = :text, status = :status WHERE id = :id'
        );
        $stmt->execute(['text' => $text, 'status' => $status, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM documents WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
