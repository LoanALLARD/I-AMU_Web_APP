<?php

namespace Models;

use PDO;

class InteractionRepository {

    private PDO $pdo;

    private int $model_id;
    private int $conversation_id;
    private string $prompt;
    private string $response;
    private string $sent_at;
    private string $latency;
    private int $input_tokens;
    private int $output_tokens;
    private int $user_feedback;
    
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    public function newInteration(int $model_id,int $conversation_id, string $prompt, string $response, int $input_tokens, int $output_tokens){
        $query = $this->pdo->prepare('
        INSERT INTO interactions (model_id,conversation_id,prompt,response,output_tokens) 
        VALUES (:model_id, :conversation_id, :prompt, :response, :output_tokens)
        ');

        $query->execute([
            'model_id'=>$model_id,
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