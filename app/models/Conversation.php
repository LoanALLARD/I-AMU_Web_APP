<?php

namespace App\Models;

use App\Core\Model;

class Conversation extends Model
{
    protected string $table = 'conversation';
    protected string $primaryKey = 'conversation_id';

    /**
     * Récupère les conversations d'un utilisateur.
     */
    public function findByUser(int $userId, bool $includeArchived = false): array
    {
        $sql = 'SELECT * FROM conversation WHERE user_id = :uid';
        $params = ['uid' => $userId];

        if (!$includeArchived) {
            $sql .= ' AND is_archived = false';
        }

        $sql .= ' ORDER BY created_at DESC';
        return $this->db()->query($sql, $params)->fetchAll();
    }

    /**
     * Récupère les conversations liées à une session.
     */
    public function findBySession(int $sessionId): array
    {
        return $this->findBy(['session_id' => $sessionId], 'created_at DESC');
    }

    /**
     * Crée une nouvelle conversation.
     */
    public function createConversation(int $userId, string $name, string $type = 'FREE', ?int $sessionId = null): int
    {
        return $this->create([
            'name'       => $name,
            'type'       => $type,
            'user_id'    => $userId,
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Archive une conversation.
     */
    public function archive(int $conversationId): bool
    {
        return $this->update($conversationId, ['is_archived' => true]);
    }
}
