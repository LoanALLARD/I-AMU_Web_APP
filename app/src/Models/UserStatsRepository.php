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
     *     input_tokens:int, output_tokens:int, avg_latency:?float
     * }
     */
    public function personalAggregate(int $userId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT c.id)                AS conversations,
                    COUNT(i.id)                         AS interactions,
                    COALESCE(SUM(i.input_tokens), 0)    AS input_tokens,
                    COALESCE(SUM(i.output_tokens), 0)   AS output_tokens,
                    AVG(i.latency)                      AS avg_latency
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
            'avg_latency'   => $row['avg_latency'] !== null ? (float) $row['avg_latency'] : null,
        ];
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
