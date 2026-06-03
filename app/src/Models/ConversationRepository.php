<?php 

namespace Models;

use PDO;

class ConversationRepository {

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function newConversation(int $user_id, int $session_id, int $model_id,string $name): ?array
    {
        $query = $this->pdo->prepare('
        INSERT into conversations (user_id,session_id,model_id,name) 
        VALUES (:user_id,:session_id,:model_id,:name)
        ');

        $query-> execute([
            'user_id'=>$user_id,
            'session_id'=>$session_id,
            'model_id'=>$model_id,
            'name'=>$name
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
}