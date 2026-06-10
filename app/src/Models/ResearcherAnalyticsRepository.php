<?php

declare(strict_types=1);

namespace Models;

use PDO;

/** Read-only AI-usage analytics, scoped to a department-id list and consenting users only. */
class ResearcherAnalyticsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Placeholder list "(:d0, :d1, ...)" plus its bound params, so the IN stays prepared.
     *
     * @param list<int> $departmentIds
     * @return array{0:string, 1:array<string,int>}
     */
    private function inClause(array $departmentIds): array
    {
        $names = [];
        $params = [];
        foreach (array_values($departmentIds) as $i => $id) {
            $key = 'd' . $i;
            $names[] = ':' . $key;
            $params[$key] = $id;
        }

        return ['(' . implode(', ', $names) . ')', $params];
    }

    /**
     * Volume + token + satisfaction aggregates over the given departments.
     *
     * @param list<int> $departmentIds
     * @return array{
     *     conversations:int, interactions:int, students:int,
     *     input_tokens:int, output_tokens:int, avg_latency:?float,
     *     feedback_positive:int, feedback_negative:int, feedback_neutral:int
     * }
     */
    public function aggregate(array $departmentIds): array
    {
        [$in, $params] = $this->inClause($departmentIds);

        $stmt = $this->pdo->prepare(
            'SELECT COUNT(DISTINCT c.id)                                       AS conversations,
                    COUNT(i.id)                                                AS interactions,
                    COUNT(DISTINCT c.user_id)                                  AS students,
                    COALESCE(SUM(i.input_tokens), 0)                           AS input_tokens,
                    COALESCE(SUM(i.output_tokens), 0)                          AS output_tokens,
                    AVG(i.latency)                                             AS avg_latency,
                    COUNT(*) FILTER (WHERE i.user_feedback = 1)                AS feedback_positive,
                    COUNT(*) FILTER (WHERE i.user_feedback = -1)               AS feedback_negative,
                    COUNT(*) FILTER (WHERE i.user_feedback = 0)                AS feedback_neutral
             FROM interactions i
             JOIN conversations c ON c.id = i.conversation_id
             JOIN users u ON u.id = c.user_id
             JOIN sessions s ON s.id = c.session_id
             JOIN resources r ON r.id = s.resource_id
             WHERE r.department_id IN ' . $in . '
               AND u.research_opposed = FALSE'
        );
        $stmt->execute($params);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch();

        return [
            'conversations'     => (int) ($row['conversations'] ?? 0),
            'interactions'      => (int) ($row['interactions'] ?? 0),
            'students'          => (int) ($row['students'] ?? 0),
            'input_tokens'      => (int) ($row['input_tokens'] ?? 0),
            'output_tokens'     => (int) ($row['output_tokens'] ?? 0),
            'avg_latency'       => $row['avg_latency'] !== null ? (float) $row['avg_latency'] : null,
            'feedback_positive' => (int) ($row['feedback_positive'] ?? 0),
            'feedback_negative' => (int) ($row['feedback_negative'] ?? 0),
            'feedback_neutral'  => (int) ($row['feedback_neutral'] ?? 0),
        ];
    }

    /**
     * Daily interaction counts over the last $days days; empty days omitted (caller fills gaps).
     *
     * @param list<int> $departmentIds
     * @return list<array{day:string, total:int}>
     */
    public function dailyActivity(array $departmentIds, int $days): array
    {
        [$in, $params] = $this->inClause($departmentIds);
        $params['days'] = $days;

        $stmt = $this->pdo->prepare(
            "SELECT to_char(date_trunc('day', i.sent_at), 'YYYY-MM-DD') AS day,
                    COUNT(*)                                            AS total
             FROM interactions i
             JOIN conversations c ON c.id = i.conversation_id
             JOIN users u ON u.id = c.user_id
             JOIN sessions s ON s.id = c.session_id
             JOIN resources r ON r.id = s.resource_id
             WHERE r.department_id IN " . $in . "
               AND u.research_opposed = FALSE
               AND i.sent_at >= NOW() - (:days || ' days')::interval
             GROUP BY day
             ORDER BY day"
        );
        $stmt->execute($params);

        /** @var list<array{day:string, total:int}> $rows */
        $rows = array_map(
            static fn (array $r): array => ['day' => (string) $r['day'], 'total' => (int) $r['total']],
            $stmt->fetchAll()
        );

        return $rows;
    }

    /**
     * Top prompt keywords over the given departments. Words are kept in their
     * typed form; the French tsvector is used only to drop stop-words, not to
     * stem (the stem reads as 'complexit', 'requet', ... — unreadable). Only
     * words used by at least $minStudents distinct consenting users surface.
     * 'total' is the occurrence count of every surfaced word, for share-of math.
     *
     * @param list<int> $departmentIds
     * @return array{total:int, words:list<array{word:string, occurrences:int, students:int}>}
     */
    public function topKeywords(array $departmentIds, int $limit, int $minStudents): array
    {
        [$in, $params] = $this->inClause($departmentIds);

        $stmt = $this->pdo->prepare(
            "WITH tokens AS (
                 SELECT c.user_id,
                        lower((regexp_matches(i.prompt, '[[:alpha:]]{3,}', 'g'))[1]) AS word
                 FROM interactions i
                 JOIN conversations c ON c.id = i.conversation_id
                 JOIN users u ON u.id = c.user_id
                 JOIN sessions s ON s.id = c.session_id
                 JOIN resources r ON r.id = s.resource_id
                 WHERE r.department_id IN " . $in . "
                   AND u.research_opposed = FALSE
             ),
             counted AS (
                 SELECT word,
                        COUNT(*)                AS occurrences,
                        COUNT(DISTINCT user_id) AS students
                 FROM tokens
                 WHERE to_tsvector('french', word) <> ''::tsvector
                 GROUP BY word
             )
             SELECT word,
                    occurrences,
                    students,
                    SUM(occurrences) OVER () AS total
             FROM counted
             WHERE students >= :min_students
             ORDER BY occurrences DESC, word
             LIMIT :lim"
        );
        // LIMIT needs a real integer: emulated prepares are off, so a string-bound :lim is rejected.
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_INT);
        }
        $stmt->bindValue('min_students', $minStudents, PDO::PARAM_INT);
        $stmt->bindValue('lim', $limit, PDO::PARAM_INT);
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return [
            'total' => $rows === [] ? 0 : (int) $rows[0]['total'],
            'words' => array_map(
                static fn (array $r): array => [
                    'word'        => (string) $r['word'],
                    'occurrences' => (int) $r['occurrences'],
                    'students'    => (int) $r['students'],
                ],
                $rows
            ),
        ];
    }

    /**
     * Prompt length and conversation depth over the given departments. Lengths
     * are measured on the raw prompt (characters) and on whitespace-split words;
     * depth is the interaction count per conversation. Consent-filtered.
     *
     * @param list<int> $departmentIds
     * @return array{avg_prompt_chars:?float, avg_prompt_words:?float, avg_interactions_per_conversation:?float}
     */
    public function promptShape(array $departmentIds): array
    {
        [$in, $params] = $this->inClause($departmentIds);

        $stmt = $this->pdo->prepare(
            "WITH scoped AS (
                 SELECT i.id,
                        i.conversation_id,
                        char_length(i.prompt)                                          AS chars,
                        array_length(regexp_split_to_array(trim(i.prompt), '\\s+'), 1) AS words
                 FROM interactions i
                 JOIN conversations c ON c.id = i.conversation_id
                 JOIN users u ON u.id = c.user_id
                 JOIN sessions s ON s.id = c.session_id
                 JOIN resources r ON r.id = s.resource_id
                 WHERE r.department_id IN " . $in . "
                   AND u.research_opposed = FALSE
             )
             SELECT AVG(chars) AS avg_prompt_chars,
                    AVG(words) AS avg_prompt_words,
                    (SELECT AVG(cnt) FROM (
                        SELECT COUNT(*) AS cnt FROM scoped GROUP BY conversation_id
                    ) per_conv) AS avg_interactions_per_conversation
             FROM scoped"
        );
        $stmt->execute($params);
        /** @var array<string, mixed> $row */
        $row = $stmt->fetch();

        $depth = $row['avg_interactions_per_conversation'];

        return [
            'avg_prompt_chars' => $row['avg_prompt_chars'] !== null ? (float) $row['avg_prompt_chars'] : null,
            'avg_prompt_words' => $row['avg_prompt_words'] !== null ? (float) $row['avg_prompt_words'] : null,
            'avg_interactions_per_conversation' => $depth !== null ? (float) $depth : null,
        ];
    }

    /**
     * Flat research corpus: one row per interaction over the given departments,
     * restricted to consenting users (research_opposed = FALSE). No direct
     * identifier (name, email, student number) ever leaves the database here;
     * only the user id is read, so the service can pseudonymise it at export
     * time. Feeds both the JSON and the CSV researcher exports.
     *
     * @param list<int> $departmentIds
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $departmentIds): array
    {
        if ($departmentIds === []) {
            return [];
        }

        [$in, $params] = $this->inClause($departmentIds);

        $stmt = $this->pdo->prepare(
            'SELECT p.name        AS place_name,
                    d.name        AS department_name,
                    s.id          AS session_id,
                    s.name        AS session_name,
                    s.access_code AS session_access_code,
                    c.user_id     AS student_id,
                    c.id          AS conversation_id,
                    c.name        AS conversation_name,
                    c.created_at  AS conversation_created,
                    c.is_archived,
                    i.id          AS interaction_id,
                    i.prompt,
                    i.response,
                    m.name        AS model_name,
                    i.input_tokens,
                    i.output_tokens,
                    i.latency,
                    i.user_feedback,
                    i.sent_at
             FROM interactions i
             JOIN conversations c ON c.id = i.conversation_id
             JOIN users u ON u.id = c.user_id
             JOIN sessions s ON s.id = c.session_id
             JOIN resources r ON r.id = s.resource_id
             JOIN departments d ON d.id = r.department_id
             JOIN places p ON p.id = d.place_id
             LEFT JOIN models m ON m.id = c.model_id
             WHERE r.department_id IN ' . $in . '
               AND u.research_opposed = FALSE
             ORDER BY p.name, d.name, s.id, c.user_id, c.id, i.sent_at, i.id'
        );
        $stmt->execute($params);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }
}
