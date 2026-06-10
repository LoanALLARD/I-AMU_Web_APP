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
                s.post_prompt_override, s.instructions, s.max_input_size, s.max_tokens,
                s.documents_max_bytes
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
     * Sessions the teacher SUPERVISES read-only: those of resources they are
     * attached to via `teacher_resources` but do NOT own. The owner is excluded
     * (they already see the session through findAllByTeacher with full rights).
     *
     * @return list<array<string, mixed>>
     */
    public function findSupervisedByTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT
            . ' JOIN teacher_resources tr ON tr.resource_id = r.id'
            . ' WHERE tr.teacher_id = :tid AND r.owner_id <> :owner'
            . ' ORDER BY s.starts_at DESC NULLS FIRST, s.id DESC'
        );
        $stmt->execute(['tid' => $teacherId, 'owner' => $teacherId]);

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
                 pre_prompt_override, post_prompt_override, instructions, max_input_size, max_tokens,
                 documents_max_bytes)
             VALUES
                (:resource_id, :name, :type, :status, :starts_at, :ends_at, :closed_at,
                 :pre_prompt_override, :post_prompt_override, :instructions, :max_input_size, :max_tokens,
                 :documents_max_bytes)
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
            'max_tokens'          => $data['max_tokens'],
            'documents_max_bytes'     => $data['documents_max_bytes'],
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
                max_input_size = :max_input_size, max_tokens = :max_tokens,
                documents_max_bytes = :documents_max_bytes
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
            'max_tokens'           => $data['max_tokens'],
            'documents_max_bytes'     => $data['documents_max_bytes'],
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
     * Names of the file formats a session authorises for student document
     * imports. An empty list means imports are disabled for that session.
     *
     * @return list<string>
     */
    public function authorizedFormatsOf(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ff.name
               FROM session_file_formats sff
               JOIN file_formats ff ON ff.id = sff.file_format_id
              WHERE sff.session_id = :sid
              ORDER BY ff.id'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<string> $names */
        $names = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    /**
     * Replaces the whole authorised-format set in one transaction. Unknown
     * names are ignored; an empty set disables imports for the session.
     *
     * @param list<string> $formatNames
     */
    public function setAuthorizedFormats(int $sessionId, array $formatNames): void
    {
        $unique = array_values(array_unique(array_map('strval', $formatNames)));

        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare('DELETE FROM session_file_formats WHERE session_id = :sid');
            $delete->execute(['sid' => $sessionId]);

            $insert = $this->pdo->prepare(
                'INSERT INTO session_file_formats (session_id, file_format_id)
                 SELECT :sid, id FROM file_formats WHERE name = :name'
            );
            foreach ($unique as $name) {
                $insert->execute(['sid' => $sessionId, 'name' => $name]);
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Every file format name the platform knows, for the session form.
     *
     * @return list<string>
     */
    public function listAllFileFormats(): array
    {
        /** @var list<string> $names */
        $names = $this->pdo->query('SELECT name FROM file_formats ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);

        return $names;
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
                 u.id            AS student_id,
                 u.first_name,
                 u.last_name,
                 c.id            AS conversation_id,
                 c.name          AS conversation_name,
                 c.created_at    AS conversation_created,
                 COUNT(i.id)     AS prompt_count,
                 MAX(i.sent_at)  AS last_activity,
                 mdl.name        AS last_model,
                 e.is_active::int AS is_active
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN conversations c ON c.user_id = e.student_id AND c.session_id = e.session_id
               LEFT JOIN models mdl ON mdl.id = c.model_id
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE e.session_id = :sid
              GROUP BY u.id, u.first_name, u.last_name, c.id, c.name, c.created_at, mdl.name, e.is_active
              ORDER BY u.last_name, u.first_name, c.created_at'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Id of the first model authorised for a session (from session_models),
     * or null when the session has none.
     */
    public function firstModelForSession(int $sessionId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT model_id FROM session_models WHERE session_id = :sid ORDER BY model_id LIMIT 1'
        );
        $stmt->execute(['sid' => $sessionId]);
        $id = $stmt->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Prompt/response history of a single conversation, scoped to the session
     * for safety (read-only supervision).
     *
     * @return list<array<string, mixed>>
     */
    public function interactionsOfConversation(int $conversationId, int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.prompt, i.response, i.sent_at, m.name AS model_name
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
               JOIN models m ON m.id = c.model_id
              WHERE i.conversation_id = :cid AND c.session_id = :sid
              ORDER BY i.sent_at ASC'
        );
        $stmt->execute(['cid' => $conversationId, 'sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Flat research export of a session: one row per (student × conversation ×
     * interaction). Enrolled students with no conversation, and conversations
     * with no interaction, still appear (LEFT JOINs). The caller nests the rows
     * into students -> conversations -> interactions.
     *
     * No anonymisation (per the project's GDPR stance) — identity is included.
     *
     * @return list<array<string, mixed>>
     */
    public function exportRows(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                 u.id            AS student_id,
                 u.first_name,
                 u.last_name,
                 u.email,
                 st.student_number,
                 c.id            AS conversation_id,
                 c.name          AS conversation_name,
                 c.created_at    AS conversation_created,
                 c.is_archived,
                 i.id            AS interaction_id,
                 i.prompt,
                 i.response,
                 i.input_tokens,
                 i.output_tokens,
                 i.latency,
                 i.user_feedback,
                 i.sent_at,
                 m.name          AS model_name
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN students st ON st.id = u.id
               LEFT JOIN conversations c ON c.user_id = e.student_id AND c.session_id = e.session_id
               LEFT JOIN interactions i ON i.conversation_id = c.id
               LEFT JOIN models m ON m.id = c.model_id
              WHERE e.session_id = :sid
              ORDER BY u.last_name, u.first_name, c.id, i.sent_at, i.id'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Enrolled students of a session (one row each), for the export filter UI.
     *
     * @return list<array<string, mixed>>
     */
    public function enrolledStudents(int $sessionId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, st.student_number
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN students st ON st.id = u.id
              WHERE e.session_id = :sid
              ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['sid' => $sessionId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Exam sessions a student is enrolled in that may currently be running.
     *
     * Only EXAM-type sessions with a non-terminal status are returned; the
     * caller hydrates Domain\Session and uses isActive() to confirm the
     * lock (time-based expiry is resolved there, not in SQL).
     *
     * @return list<array<string, mixed>>
     */
    public function examCandidatesForStudent(int $studentId): array
    {
        $stmt = $this->pdo->prepare(
            self::SELECT
            . ' JOIN enrollments e ON e.session_id = s.id'
            . " WHERE e.student_id = :sid AND s.type = 'EXAM'"
            . " AND s.status IN ('SCHEDULED', 'ACTIVE')"
        );
        $stmt->execute(['sid' => $studentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Return the value of preprompt in the table Session in the DB.
     * 
     * @return array<string, mixed>
     */
    public function getPreAndPostPromptBySessionId(int $sessionId): array {
        $query = $this->pdo->prepare(
            'SELECT pre_prompt_override,post_prompt_override
            FROM sessions 
            where id = :id
            '
        );
        $query->execute(['id'=>$sessionId]);

        $rows = $query->fetch();

        return $rows;

    }
    public function tokenUsageForStudent(int $studentId, int $sessionId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(COALESCE(i.input_tokens, 0) + COALESCE(i.output_tokens, 0)), 0)
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
              WHERE c.user_id = :uid AND c.session_id = :sid'
        );
        $stmt->execute(['uid' => $studentId, 'sid' => $sessionId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Aggregate statistics for a session: overview KPIs, per-student activity
     * (enrolled students with no activity included), prompts-per-hour, feedback
     * distribution and per-model usage. Raw numbers — the Service derives rates,
     * percentages and chart scales.
     *
     * @return array{
     *   overview: array<string, mixed>,
     *   perStudent: list<array<string, mixed>>,
     *   activityByHour: list<array<string, mixed>>,
     *   feedback: list<array<string, mixed>>,
     *   byModel: list<array<string, mixed>>
     * }
     */
    public function statsForSession(int $sessionId): array
    {
        $overviewStmt = $this->pdo->prepare(
            'SELECT
                (SELECT COUNT(*) FROM enrollments WHERE session_id = :sid) AS enrolled,
                COUNT(DISTINCT c.user_id) FILTER (WHERE i.id IS NOT NULL) AS active_participants,
                COUNT(i.id) AS prompts,
                COALESCE(SUM(i.input_tokens), 0)  AS input_tokens,
                COALESCE(SUM(i.output_tokens), 0) AS output_tokens,
                COALESCE(ROUND(AVG(i.latency)), 0) AS avg_latency,
                COALESCE(ROUND(percentile_cont(0.5) WITHIN GROUP (ORDER BY i.latency)), 0) AS median_latency,
                COALESCE(ROUND(AVG(char_length(i.prompt))), 0)   AS avg_prompt_len,
                COALESCE(ROUND(AVG(char_length(i.response))), 0) AS avg_response_len
             FROM conversations c
             LEFT JOIN interactions i ON i.conversation_id = c.id
             WHERE c.session_id = :sid'
        );
        $overviewStmt->execute(['sid' => $sessionId]);
        /** @var array<string, mixed> $overview */
        $overview = $overviewStmt->fetch() ?: [];

        $perStudentStmt = $this->pdo->prepare(
            'SELECT u.id, u.first_name, u.last_name, st.student_number,
                    COUNT(DISTINCT c.id) AS conversations,
                    COUNT(i.id) AS prompts,
                    COALESCE(SUM(i.input_tokens + i.output_tokens), 0) AS tokens,
                    MAX(i.sent_at) AS last_activity,
                    COUNT(i.id) FILTER (WHERE i.user_feedback = 1)  AS feedback_up,
                    COUNT(i.id) FILTER (WHERE i.user_feedback = -1) AS feedback_down
               FROM enrollments e
               JOIN users u ON u.id = e.student_id
               LEFT JOIN students st ON st.id = u.id
               LEFT JOIN conversations c ON c.user_id = e.student_id AND c.session_id = e.session_id
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE e.session_id = :sid
              GROUP BY u.id, u.first_name, u.last_name, st.student_number
              ORDER BY prompts DESC, u.last_name, u.first_name'
        );
        $perStudentStmt->execute(['sid' => $sessionId]);
        /** @var list<array<string, mixed>> $perStudent */
        $perStudent = $perStudentStmt->fetchAll();

        $activityStmt = $this->pdo->prepare(
            'SELECT date_trunc(\'hour\', i.sent_at) AS bucket, COUNT(*) AS prompts
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
              WHERE c.session_id = :sid
              GROUP BY bucket
              ORDER BY bucket'
        );
        $activityStmt->execute(['sid' => $sessionId]);
        /** @var list<array<string, mixed>> $activityByHour */
        $activityByHour = $activityStmt->fetchAll();

        $feedbackStmt = $this->pdo->prepare(
            'SELECT i.user_feedback AS feedback, COUNT(*) AS n
               FROM interactions i
               JOIN conversations c ON c.id = i.conversation_id
              WHERE c.session_id = :sid
              GROUP BY i.user_feedback'
        );
        $feedbackStmt->execute(['sid' => $sessionId]);
        /** @var list<array<string, mixed>> $feedback */
        $feedback = $feedbackStmt->fetchAll();

        $byModelStmt = $this->pdo->prepare(
            'SELECT m.name AS model_name,
                    COUNT(i.id) AS prompts,
                    COALESCE(SUM(i.input_tokens + i.output_tokens), 0) AS tokens
               FROM conversations c
               JOIN models m ON m.id = c.model_id
               LEFT JOIN interactions i ON i.conversation_id = c.id
              WHERE c.session_id = :sid
              GROUP BY m.name
              ORDER BY prompts DESC'
        );
        $byModelStmt->execute(['sid' => $sessionId]);
        /** @var list<array<string, mixed>> $byModel */
        $byModel = $byModelStmt->fetchAll();

        return [
            'overview'       => $overview,
            'perStudent'     => $perStudent,
            'activityByHour' => $activityByHour,
            'feedback'       => $feedback,
            'byModel'        => $byModel,
        ];
    }
}
