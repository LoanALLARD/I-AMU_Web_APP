<?php 

namespace Models;

use PDO;

class ConversationRepository {

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * Creates a conversation and returns its new id. `session_id` is null
     * for a free-mode conversation. (The `conversations` table has no
     * model_id column — the model is chosen per interaction.)
     */
    public function newConversation(int $user_id, ?int $session_id, string $name): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO conversations (user_id, session_id, name)
             VALUES (:user_id, :session_id, :name)
             RETURNING id'
        );
        $stmt->execute([
            'user_id'    => $user_id,
            'session_id' => $session_id,
            'name'       => $name,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Conversations a user owns in a given session, with their prompt count,
     * most recent first. Used by the session-environment sidebar.
     *
     * @return list<array<string, mixed>>
     */
    public function listByUserAndSession(int $userId, int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, COUNT(i.id) AS prompt_count
               FROM conversations c
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE c.user_id = :u AND c.session_id = :s AND c.is_archived = FALSE
              GROUP BY c.id, c.name, c.created_at
              ORDER BY c.created_at DESC, c.id DESC'
        );
        $stmt->execute(['u' => $userId, 's' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Free-mode conversations (no session) a user owns, most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function listFreeByUser(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, COUNT(i.id) AS prompt_count
               FROM conversations c
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE c.user_id = :u AND c.session_id IS NULL AND c.is_archived = FALSE
              GROUP BY c.id, c.name, c.created_at
              ORDER BY c.created_at DESC, c.id DESC'
        );
        $stmt->execute(['u' => $userId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    public function countByUserAndSession(int $userId, int $sessionId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM conversations WHERE user_id = :u AND session_id = :s'
        );
        $stmt->execute(['u' => $userId, 's' => $sessionId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConversationByUserId(int $user_id, int $conversation_id): ?array
    {
        $query = $this->pdo->prepare('
        SELECT * FROM conversations where user_id = :user_id AND id = :id
        ');

        $query->execute([
            'user_id'=>$user_id,
            'id'=>$conversation_id
        ]);
        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Id of the (first) conversation a user already has for a session, or null.
     * Used to keep "join session" idempotent.
     */
    public function findIdByUserAndSession(int $userId, int $sessionId): ?int
    {
        $query = $this->pdo->prepare('
        SELECT id FROM conversations WHERE user_id = :u AND session_id = :s ORDER BY id LIMIT 1
        ');

        $query->execute(['u' => $userId, 's' => $sessionId]);
        $id = $query->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}