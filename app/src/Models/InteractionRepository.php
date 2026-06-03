<?php

namespace Models;

use PDO;

class InteractionRepository {

    private PDO $pdo;
    
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function newInteration(int $conversation_id, string $prompt, string $response, int $input_tokens, int $output_tokens): ?array
    {
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

        $idGenere = $this->pdo->lastInsertId();

        if (!$idGenere) {
            return null;
        }

        $querySelect = $this->pdo->prepare('SELECT * FROM interactions WHERE id = :id');
        $querySelect->execute(['id' => $idGenere]);
        
        $result = $querySelect->fetch();

        return $result ?: null; 
    }

    public function setContext(string $metadata, int $interaction_id){
        $query = $this->pdo->prepare('
            UPDATE interactions set api_metadata = :metadata where id = :id
        ');
        
        $query->execute([
            'metadata' => $metadata,
            'id'       => $interaction_id
        ]);

        $idGenere = $this->pdo->lastInsertId();

            if (!$idGenere) {
                return null;
            }
            
        return TRUE;
    }   
}