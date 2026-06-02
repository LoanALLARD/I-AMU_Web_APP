<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Application\DTOs\ConversationView;
use App\Application\Ports\ConversationRepositoryInterface;

/**
 * PostgreSQL implementation of {@see ConversationRepositoryInterface}.
 *
 * Uses `INSERT ... RETURNING id` (same pattern as the other repositories)
 * so the new id comes back without a separate sequence lookup.
 */
final class PdoConversationRepository extends PdoRepository implements ConversationRepositoryInterface
{
    public function findIdByUserAndSession(int $userId, int $sessionId): ?int
    {
        $row = $this->fetchOne(
            'SELECT id FROM conversations
             WHERE user_id = :u AND session_id = :s
             ORDER BY id
             LIMIT 1',
            ['u' => $userId, 's' => $sessionId]
        );

        return $row === null ? null : (int) $row['id'];
    }

    public function create(int $userId, int $sessionId, string $name): int
    {
        $row = $this->fetchOne(
            'INSERT INTO conversations (user_id, session_id, name)
             VALUES (:u, :s, :n)
             RETURNING id',
            ['u' => $userId, 's' => $sessionId, 'n' => $name]
        );

        return (int) $row['id'];
    }

    public function findOwnedById(int $conversationId, int $userId): ?ConversationView
    {
        $row = $this->fetchOne(
            'SELECT id, name, session_id
             FROM conversations
             WHERE id = :id AND user_id = :u',
            ['id' => $conversationId, 'u' => $userId]
        );

        return $row === null ? null : ConversationView::fromRow($row);
    }

    public function findRecentByUser(int $userId): array
    {
        $rows = $this->fetchAll(
            'SELECT id, name, session_id
             FROM conversations
             WHERE user_id = :u AND is_archived = FALSE
             ORDER BY created_at DESC, id DESC
             LIMIT 50',
            ['u' => $userId]
        );

        return array_map(static fn (array $r) => ConversationView::fromRow($r), $rows);
    }
}
