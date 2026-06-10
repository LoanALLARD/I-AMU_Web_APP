<?php

declare(strict_types=1);

namespace Models;

use PDO;

/** Read-only personal AI-usage stats, scoped to a single user's own conversations. */
class UserStatsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Volume + token aggregates over the user's own conversations.
     *
     * @return array{
     *     conversations:int, interactions:int,
     *     input_tokens:int, output_tokens:int, last_activity:?string
     * }
     */
    public function personalAggregate(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT c.id)                AS conversations,
                    COUNT(i.id)                         AS interactions,
                    COALESCE(SUM(i.input_tokens), 0)    AS input_tokens,
                    COALESCE(SUM(i.output_tokens), 0)   AS output_tokens,
                    MAX(i.sent_at)                      AS last_activity
             FROM conversations c
             LEFT JOIN interactions i ON i.conversation_id = c.id
             WHERE c.user_id = :user_id'
        );
        $stmt->execute(['user_id' => $userId]);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch();

        return [
            'conversations' => (int) ($row['conversations'] ?? 0),
            'interactions'  => (int) ($row['interactions'] ?? 0),
            'input_tokens'  => (int) ($row['input_tokens'] ?? 0),
            'output_tokens' => (int) ($row['output_tokens'] ?? 0),
            'last_activity' => $row['last_activity'] !== null ? (string) $row['last_activity'] : null,
        ];
    }

    /**
     * The model the user has used most (by interaction count), or null when none.
     * Conversations without interactions do not count toward usage.
     *
     * @return array{name:string, interactions:int}|null
     */
    public function topModel(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.name             AS name,
                    COUNT(i.id)        AS interactions
             FROM conversations c
             JOIN interactions i ON i.conversation_id = c.id
             JOIN models m ON m.id = c.model_id
             WHERE c.user_id = :user_id
             GROUP BY m.id, m.name
             ORDER BY interactions DESC, m.name ASC
             LIMIT 1'
        );
        $stmt->execute(['user_id' => $userId]);
        $row = $stmt->fetch();

        if ($row === false) {
            return null;
        }

        return ['name' => (string) $row['name'], 'interactions' => (int) $row['interactions']];
    }

    /**
     * Token totals grouped by the model that produced them, for energy estimation
     * (a bigger model costs more per token). Only models that actually ran appear.
     *
     * @return list<array{model_size:?string, input_tokens:int, output_tokens:int}>
     */
    public function tokensByModel(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT m.size                          AS model_size,
                    COALESCE(SUM(i.input_tokens), 0)  AS input_tokens,
                    COALESCE(SUM(i.output_tokens), 0) AS output_tokens
             FROM conversations c
             JOIN interactions i ON i.conversation_id = c.id
             JOIN models m ON m.id = c.model_id
             WHERE c.user_id = :user_id
             GROUP BY m.id, m.size'
        );
        $stmt->execute(['user_id' => $userId]);

        /** @var list<array{model_size:?string, input_tokens:int, output_tokens:int}> $rows */
        $rows = array_map(
            static fn (array $r): array => [
                'model_size'    => $r['model_size'] !== null ? (string) $r['model_size'] : null,
                'input_tokens'  => (int) $r['input_tokens'],
                'output_tokens' => (int) $r['output_tokens'],
            ],
            $stmt->fetchAll()
        );

        return $rows;
    }

    /**
     * Daily interaction counts over the last $days days; empty days omitted (caller fills gaps).
     *
     * @return list<array{day:string, total:int}>
     */
    public function dailyActivity(int $userId, int $days): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT to_char(date_trunc('day', i.sent_at), 'YYYY-MM-DD') AS day,
                    COUNT(*)                                            AS total
             FROM interactions i
             JOIN conversations c ON c.id = i.conversation_id
             WHERE c.user_id = :user_id
               AND i.sent_at >= NOW() - (:days || ' days')::interval
             GROUP BY day
             ORDER BY day"
        );
        $stmt->execute(['user_id' => $userId, 'days' => $days]);

        /** @var list<array{day:string, total:int}> $rows */
        $rows = array_map(
            static fn (array $r): array => ['day' => (string) $r['day'], 'total' => (int) $r['total']],
            $stmt->fetchAll()
        );

        return $rows;
    }
}
