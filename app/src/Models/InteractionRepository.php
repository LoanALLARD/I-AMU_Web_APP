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
     *
     * @return array<string, mixed>|null
     */
    public function newInteration(
        int $conversation_id,
        string $prompt,
        string $response,
        int $input_tokens,
        int $output_tokens
    ): ?array {
        $query = $this->pdo->prepare(
            'INSERT INTO interactions
                (conversation_id, prompt, response, input_tokens, output_tokens)
             VALUES
                (:conversation_id, :prompt, :response, :input_tokens, :output_tokens)'
        );

        $query->execute([
            'conversation_id' => $conversation_id,
            'prompt'          => $prompt,
            'response'        => $response,
            'input_tokens'    => $input_tokens > 0 ? $input_tokens : null,
            'output_tokens'   => $output_tokens >= 0 ? $output_tokens : null,
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
            'SELECT i.id, i.prompt, i.response, i.sent_at, m.name AS model_name
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
               JOIN models m ON m.id = c.model_id
              WHERE i.conversation_id = :cid
              ORDER BY i.sent_at ASC, i.id ASC'
        );
        $stmt->execute(['cid' => $conversationId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    public function setContext(string $metadata, int $interaction_id): ?bool {
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

    /**
     * @return array<array<string, mixed>>
     */
    public function getInteractionsByConversationId(int $conversation_id): array
    {
        $query = $this->pdo->prepare('
        SELECT * FROM interactions where conversation_id = :conversation_id
        ');

        $query->execute([
            'conversation_id'=>$conversation_id
        ]);

        return $query->fetchAll();
    }
}