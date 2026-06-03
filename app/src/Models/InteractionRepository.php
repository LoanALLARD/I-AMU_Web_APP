<?php

namespace Models;

use PDO;

class InteractionRepository {

    private PDO $pdo;
    
    public function __construct(PDO $pdo){
        $this->pdo = $pdo;
    }

    /**
     * Persists a prompt/response turn. `model_id` is NOT NULL in the schema,
     * so it must always be provided. Token counts honour the table CHECKs
     * (input_tokens > 0 or NULL ; output_tokens >= 0 or NULL).
     */
    public function newInteration(
        int $conversation_id,
        int $model_id,
        string $prompt,
        string $response,
        int $input_tokens,
        int $output_tokens
    ): void {
        $query = $this->pdo->prepare(
            'INSERT INTO interactions
                (conversation_id, model_id, prompt, response, input_tokens, output_tokens)
             VALUES
                (:conversation_id, :model_id, :prompt, :response, :input_tokens, :output_tokens)'
        );

        $query->execute([
            'conversation_id' => $conversation_id,
            'model_id'        => $model_id,
            'prompt'          => $prompt,
            'response'        => $response,
            'input_tokens'    => $input_tokens > 0 ? $input_tokens : null,
            'output_tokens'   => $output_tokens >= 0 ? $output_tokens : null,
        ]);
    }

    /**
     * Full prompt/response history of a conversation, oldest first, with the
     * model name of each turn. Ownership is enforced by the caller: the chat
     * environment only asks for messages of a conversation it has already
     * resolved as belonging to the user.
     *
     * @return list<array<string, mixed>>
     */
    public function listByConversation(int $conversationId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.prompt, i.response, i.sent_at, m.name AS model_name
               FROM interactions i
               JOIN models m ON m.id = i.model_id
              WHERE i.conversation_id = :cid
              ORDER BY i.sent_at ASC, i.id ASC'
        );
        $stmt->execute(['cid' => $conversationId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }
}