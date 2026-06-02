<?php

namespace Models;

use PDO;

class InteractionRepository {

    private PDO $pdo;
    
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function newInteration(int $conversation_id, string $prompt, string $response, int $input_tokens, int $output_tokens){
        $query = $this->pdo->prepare('
        INSERT INTO interactions (conversation_id,prompt,response,output_tokens)
        VALUES (:conversation_id, :prompt, :response, :output_tokens)
        ');

        $query->execute([
            'conversation_id'=>$conversation_id,
            'prompt'=>$prompt,
            'response'=>$response,
            // 'input_tokens'=>$input_tokens,
            'output_tokens'=>$output_tokens
            ]);

        $result = $query->fetch();

        if ($result === false) {
            return null;
        }

        return $result;
    }
}