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
    public function newConversation(int $user_id,int $model_id, ?int $session_id, string $name)
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO conversations (user_id, session_id, model_id, name)
             VALUES (:user_id, :session_id, :model_id, :name)'
        );
        $stmt->execute([
            'user_id'    => $user_id,
            'session_id' => $session_id,
            'model_id'   => $model_id,
            'name'       => $name,
        ]);

        $idGenere = $this->pdo->lastInsertId();

        if (!$idGenere) {
            return null;
        }

        $querySelect = $this->pdo->prepare('SELECT * FROM conversations WHERE id = :id');
        $querySelect->execute(['id' => $idGenere]);
        
        $result = $querySelect->fetch();

        return $result ?: null; 
    }

    /**
     * Conversations a user owns in a given session, with their prompt count,
     * most recent first. `$archived` flips the view between the active and the
     * archived list. Used by the session-environment sidebar.
     *
     * @return list<array<string, mixed>>
     */
    public function listByUserAndSession(int $userId, int $sessionId, bool $archived = false): array
    {
        // Controlled boolean literal (never user input) — safe to inline,
        // and avoids PDO boolean-binding quirks on the pgsql driver.
        $flag = $archived ? 'TRUE' : 'FALSE';
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, COUNT(i.id) AS prompt_count
               FROM conversations c
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE c.user_id = :u AND c.session_id = :s AND c.is_archived = ' . $flag . '
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
     * `$archived` flips between the active and the archived list.
     *
     * @return list<array<string, mixed>>
     */
    public function listFreeByUser(int $userId, bool $archived = false): array
    {
        $flag = $archived ? 'TRUE' : 'FALSE';
        $stmt = $this->pdo->prepare(
            'SELECT c.id, c.name, COUNT(i.id) AS prompt_count
               FROM conversations c
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE c.user_id = :u AND c.session_id IS NULL AND c.is_archived = ' . $flag . '
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
     * @return array<int>|null
     */
    public function getContextByConversationIdAndUserId(int $conversation_id, int $user_id){
        $query = $this->pdo->prepare('
            select i.api_metadata
            from interactions i 
            join conversations c on c.id = i.conversation_id
            where c.user_id = :user_id
            and c.id = :conversation_id
            and i.api_metadata IS NOT NULL 
            order by i.id desc
            limit 1;
        ');

        $query->execute([
            'user_id'=> $user_id,
            'conversation_id'=>$conversation_id
        ]);

        $result = $query->fetch(PDO::FETCH_ASSOC); 

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

    /**
     * Renames a conversation the user owns. The `user_id` predicate scopes
     * the write to the owner so a forged id cannot touch another user's row.
     */
    public function rename(int $userId, int $conversationId, string $name): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE conversations SET name = :name WHERE id = :id AND user_id = :u'
        );
        $stmt->execute(['name' => $name, 'id' => $conversationId, 'u' => $userId]);
    }

    /**
     * Soft-archives a conversation the user owns (hidden from the sidebar
     * lists, which all filter on `is_archived = FALSE`). Ownership-scoped.
     */
    public function archive(int $userId, int $conversationId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE conversations SET is_archived = TRUE WHERE id = :id AND user_id = :u'
        );
        $stmt->execute(['id' => $conversationId, 'u' => $userId]);
    }

    /**
     * Restores an archived conversation the user owns. Ownership-scoped.
     */
    public function unarchive(int $userId, int $conversationId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE conversations SET is_archived = FALSE WHERE id = :id AND user_id = :u'
        );
        $stmt->execute(['id' => $conversationId, 'u' => $userId]);
    }
}