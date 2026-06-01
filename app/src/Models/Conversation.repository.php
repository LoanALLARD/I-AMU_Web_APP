<?php 

namespace Models;

use PDO;

class ConversationRepository {

    private PDO $pdo;

    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function newConversation(int $user_id, int $session_id, string $name){
        $query = $this->pdo->prepare('
        INSERT into conversations (user_id,session_id,name) 
        VALUES (:user_id,:session_id,:name)
        ');

        $query-> execute([
            'user_id'=>$user_id,
            'session_id'=>$session_id,
            'name'=>$name
        ]);

        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;    
    }
}