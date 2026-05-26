<?php

namespace App\Models;

use App\Core\Model;

class Interaction extends Model
{
    protected string $table = 'interaction';
    protected string $primaryKey = 'prompt_id';

    /**
     * Récupère les interactions d'une conversation (historique de chat).
     */
    public function findByConversation(int $conversationId): array
    {
        return $this->findBy(
            ['conversation_id' => $conversationId],
            'sent_at ASC'
        );
    }

    /**
     * Enregistre un nouveau prompt + réponse.
     */
    public function saveInteraction(array $data): int
    {
        return $this->create([
            'prompt'          => $data['prompt'],
            'response'        => $data['response'] ?? null,
            'sent_at'         => date('Y-m-d H:i:s'),
            'latency'         => $data['latency'] ?? null,
            'input_tokens'    => $data['input_tokens'] ?? null,
            'output_tokens'   => $data['output_tokens'] ?? null,
            'user_feedback'   => $data['user_feedback'] ?? null,
            'conversation_id' => $data['conversation_id'],
            'model_id'        => $data['model_id'],
        ]);
    }

    /**
     * Exporte les interactions au format structuré (pour chercheurs).
     */
    public function exportForResearch(array $filters = []): array
    {
        $sql = "SELECT 
                    i.prompt_id, i.prompt, i.response, i.sent_at,
                    i.latency, i.input_tokens, i.output_tokens, i.user_feedback,
                    c.conversation_id, c.type AS conversation_type,
                    m.name AS model_name, m.version AS model_version,
                    u.user_id, s.student_number,
                    sess.session_id, sess.name AS session_name, sess.type AS session_type
                FROM interaction i
                JOIN conversation c ON i.conversation_id = c.conversation_id
                JOIN model m ON i.model_id = m.model_id
                JOIN \"user\" u ON c.user_id = u.user_id
                LEFT JOIN student s ON u.user_id = s.user_id
                LEFT JOIN session sess ON c.session_id = sess.session_id";

        $where = [];
        $params = [];

        if (!empty($filters['session_id'])) {
            $where[] = 'c.session_id = :session_id';
            $params['session_id'] = $filters['session_id'];
        }

        if (!empty($filters['from_date'])) {
            $where[] = 'i.sent_at >= :from_date';
            $params['from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $where[] = 'i.sent_at <= :to_date';
            $params['to_date'] = $filters['to_date'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        $sql .= ' ORDER BY i.sent_at ASC';

        return $this->db()->query($sql, $params)->fetchAll();
    }
}
