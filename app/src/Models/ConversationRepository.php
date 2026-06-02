<?php 

namespace Models;

use PDO;

class ConversationRepository {

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function newConversation(int $user_id, int $session_id, int $model_id, string $name){
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

    public function getConversationByUserId(int $user_id, int $conversation_id){
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

    public function getContextByConversationIdAndUserId(int $conversation_id, int $user_id){
        
    }
}