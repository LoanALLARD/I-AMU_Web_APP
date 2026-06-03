<?php

declare(strict_types=1);

namespace Models;

use PDO;
use Throwable;

/**
 * Data access for the `sessions` aggregate. Returns raw rows (the Service
 * layer hydrates Domain\Session); the owning teacher is derived from
 * `resources.owner_id` (sessions no longer carry teacher_id directly).
 */
class SessionRepository
{
    private const SELECT =
        'SELECT s.id, s.resource_id, r.owner_id AS teacher_id, s.name, s.type, s.status,
                s.access_code, s.starts_at, s.ends_at, s.closed_at, s.pre_prompt_override,
                s.post_prompt_override, s.instructions, s.max_input_size
           FROM sessions s
           JOIN resources r ON r.id = s.resource_id';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE s.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByAccessCode(string $code): ?array
    {
        $stmt = $this->pdo->prepare(self::SELECT . ' WHERE s.access_code = :code');
        $stmt->execute(['code' => $code]);

        return $stmt->fetch() ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT . ' WHERE r.owner_id = :tid ORDER BY s.starts_at DESC NULLS FIRST, s.id DESC'
        );
        $stmt->execute(['tid' => $teacherId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Inserts a session. `access_code` is left to the database trigger
     * `trg_generate_session_access_code`, which fills it when the status is
     * SCHEDULED or ACTIVE. The generated value (or null for a draft) is
     * returned alongside the new id.
     *
     * @param array<string, scalar|null> $data
     * @return array{id: int, access_code: ?string}
     */
    public function insert(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO sessions
                (resource_id, name, type, status, starts_at, ends_at, closed_at,
                 pre_prompt_override, post_prompt_override, instructions, max_input_size)
             VALUES
                (:resource_id, :name, :type, :status, :starts_at, :ends_at, :closed_at,
                 :pre_prompt_override, :post_prompt_override, :instructions, :max_input_size)
             RETURNING id, access_code'
        );
        $stmt->execute([
            'resource_id'          => $data['resource_id'],
            'name'                 => $data['name'],
            'type'                 => $data['type'],
            'status'               => $data['status'],
            'starts_at'            => $data['starts_at'],
            'ends_at'              => $data['ends_at'],
            'closed_at'            => $data['closed_at'],
            'pre_prompt_override'  => $data['pre_prompt_override'],
            'post_prompt_override' => $data['post_prompt_override'],
            'instructions'         => $data['instructions'],
            'max_input_size'       => $data['max_input_size'],
        ]);

        /** @var array{id: int|string, access_code: ?string} $row */
        $row = $stmt->fetch();

        return [
            'id'          => (int) $row['id'],
            'access_code' => $row['access_code'] !== null && $row['access_code'] !== ''
                ? (string) $row['access_code']
                : null,
        ];
    }

    /**
     * @param array<string, scalar|null> $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE sessions SET
                name = :name, status = :status, starts_at = :starts_at, ends_at = :ends_at,
                closed_at = :closed_at, pre_prompt_override = :pre_prompt_override,
                post_prompt_override = :post_prompt_override, instructions = :instructions,
                max_input_size = :max_input_size
             WHERE id = :id'
        );
        $stmt->execute([
            'id'                   => $id,
            'name'                 => $data['name'],
            'status'               => $data['status'],
            'starts_at'            => $data['starts_at'],
            'ends_at'              => $data['ends_at'],
            'closed_at'            => $data['closed_at'],
            'pre_prompt_override'  => $data['pre_prompt_override'],
            'post_prompt_override' => $data['post_prompt_override'],
            'instructions'         => $data['instructions'],
            'max_input_size'       => $data['max_input_size'],
        ]);
    }

    /**
     * @return list<int>
     */
    public function authorizedModelIdsOf(int $sessionId): array
    {
        $stmt = $this->pdo->prepare('SELECT model_id FROM session_models WHERE session_id = :sid ORDER BY model_id');
        $stmt->execute(['sid' => $sessionId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Replaces the whole authorised-model set in one transaction.
     *
     * @param list<int> $modelIds
     */
    public function setAuthorizedModels(int $sessionId, array $modelIds): void
    {
        $unique = array_values(array_unique(array_map('intval', $modelIds)));

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM session_models WHERE session_id = :sid');
            $delete->execute(['sid' => $sessionId]);

            $insert = $this->pdo->prepare('INSERT INTO session_models (session_id, model_id) VALUES (:sid, :mid)');
            foreach ($unique as $modelId) {
                $insert->execute(['sid' => $sessionId, 'mid' => $modelId]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Per enrolled student of a session: their conversation, prompt count,
     * last activity and last-used model. Students with no conversation yet
     * still appear (LEFT JOINs), with a zero count.
     *
     * @return list<array<string, mixed>>
     */
    public function monitorStudents(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                 u.id           AS student_id,
                 u.first_name,
                 u.last_name,
                 c.id           AS conversation_id,
                 COUNT(i.id)    AS prompt_count,
                 MAX(i.sent_at) AS last_activity,
                 (SELECT m.name
                    FROM interactions i2
                    JOIN models m ON m.id = i2.model_id
                   WHERE i2.conversation_id = c.id
                   ORDER BY i2.sent_at DESC
                   LIMIT 1) AS last_model
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN conversations c ON c.user_id = e.student_id AND c.session_id = e.session_id
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE e.session_id = :sid
              GROUP BY u.id, u.first_name, u.last_name, c.id
              ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Prompt/response history of one student for a session (read-only).
     *
     * @return list<array<string, mixed>>
     */
    public function studentTranscript(int $sessionId, int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.prompt, i.response, i.sent_at, m.name AS model_name
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
               JOIN models m ON m.id = i.model_id
              WHERE c.session_id = :sid AND c.user_id = :uid
              ORDER BY i.sent_at ASC'
        );
        $stmt->execute(['sid' => $sessionId, 'uid' => $studentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

}
