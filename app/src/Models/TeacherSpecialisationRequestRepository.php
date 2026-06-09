<?php

declare(strict_types=1);

namespace Models;

use PDO;

/** Data access for `teacher_specialisation_requests`. */
class TeacherSpecialisationRequestRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * The teacher's request decision timestamps, or null if no request exists.
     *
     * @return array{approved_at: ?string, rejected_at: ?string}|null
     */
    public function findDecision(int $teacherId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT approved_at, rejected_at FROM teacher_specialisation_requests
             WHERE teacher_id = :tid'
        );
        $stmt->execute(['tid' => $teacherId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /** Files a request; a repeat reuses the row (PK) and resets it to pending. */
    public function submitRequest(int $teacherId, ?string $request): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO teacher_specialisation_requests (teacher_id, request)
             VALUES (:tid, :request)
             ON CONFLICT (teacher_id) DO UPDATE
             SET request = EXCLUDED.request,
                 requested_at = NOW(),
                 approved_at = NULL,
                 rejected_at = NULL,
                 decided_by_id = NULL'
        );
        $stmt->execute(['tid' => $teacherId, 'request' => $request]);
    }

    /**
     * Pending requests for a department, joined with teacher identity.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingByDepartment(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT tsr.teacher_id,
                    tsr.request,
                    tsr.requested_at,
                    u.first_name,
                    u.last_name,
                    u.email
             FROM teacher_specialisation_requests tsr
             JOIN teachers t ON t.id = tsr.teacher_id
             JOIN users u ON u.id = t.id
             WHERE u.department_id = :dept
               AND tsr.approved_at IS NULL
               AND tsr.rejected_at IS NULL
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /** Approves a pending request and flips teachers.is_specialised, atomically. */
    public function approve(int $teacherId, int $adminId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE teacher_specialisation_requests
                 SET approved_at = NOW(), decided_by_id = :admin
                 WHERE teacher_id = :tid
                   AND approved_at IS NULL AND rejected_at IS NULL'
            );
            $stmt->execute(['admin' => $adminId, 'tid' => $teacherId]);
            $approved = $stmt->rowCount() > 0;

            if ($approved) {
                $this->pdo->prepare('UPDATE teachers SET is_specialised = TRUE WHERE id = :tid')
                    ->execute(['tid' => $teacherId]);
            }

            $this->pdo->commit();

            return $approved;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Rejects a pending request: stamps rejected_at and the deciding admin. */
    public function reject(int $teacherId, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE teacher_specialisation_requests
             SET rejected_at = NOW(), decided_by_id = :admin
             WHERE teacher_id = :tid
               AND approved_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['admin' => $adminId, 'tid' => $teacherId]);

        return $stmt->rowCount();
    }

    /**
     * SQL predicate: the teacher's habilitation was granted then revoked.
     * rejected_at is the most recent decision, and is_specialised is FALSE,
     * so a row that matches is currently revoked (not merely never-requested).
     */
    private const REVOKED_PREDICATE =
        'tsr.rejected_at IS NOT NULL AND tsr.approved_at IS NOT NULL AND tsr.rejected_at > tsr.approved_at';

    /** Same predicate, unaliased, for use in an UPDATE on the table itself. */
    private const REVOKED_PREDICATE_BARE =
        'rejected_at IS NOT NULL AND approved_at IS NOT NULL AND rejected_at > approved_at';

    /**
     * Habilitated teachers of a department (is_specialised = TRUE), regardless
     * of how they were habilitated (direct flag or approved request).
     *
     * @return list<array<string, mixed>>
     */
    public function findHabilitatedByDepartment(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS teacher_id,
                    u.first_name,
                    u.last_name,
                    u.email
             FROM teachers t
             JOIN users u ON u.id = t.id
             WHERE u.department_id = :dept
               AND t.is_specialised = TRUE
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Teachers whose habilitation was granted then revoked. They are no longer
     * habilitated but the history is kept, so the admin can re-habilitate them.
     *
     * @return list<array<string, mixed>>
     */
    public function findRevokedByDepartment(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS teacher_id,
                    u.first_name,
                    u.last_name,
                    u.email
             FROM teacher_specialisation_requests tsr
             JOIN teachers t ON t.id = tsr.teacher_id
             JOIN users u ON u.id = t.id
             WHERE u.department_id = :dept
               AND t.is_specialised = FALSE
               AND ' . self::REVOKED_PREDICATE . '
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Identity row for a single teacher, to re-render its table row after an
     * action. Scoped to the department so it cannot leak others.
     *
     * @return array{teacher_id:int, first_name:string, last_name:string, email:string}|null
     */
    public function findRow(int $teacherId, int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS teacher_id, u.first_name, u.last_name, u.email
             FROM teachers t
             JOIN users u ON u.id = t.id
             WHERE t.id = :tid AND u.department_id = :dept'
        );
        $stmt->execute(['tid' => $teacherId, 'dept' => $departmentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Revokes a teacher's habilitation: clears is_specialised and stamps
     * rejected_at. A teacher habilitated directly (no request row) gets one
     * created so the revocation is tracked. Returns whether anything changed.
     */
    public function revoke(int $teacherId, int $adminId): bool
    {
        $this->pdo->beginTransaction();
        try {
            $cleared = $this->pdo->prepare(
                'UPDATE teachers SET is_specialised = FALSE
                 WHERE id = :tid AND is_specialised = TRUE'
            );
            $cleared->execute(['tid' => $teacherId]);
            $changed = $cleared->rowCount() > 0;

            if ($changed) {
                // A teacher habilitated directly has no request row: synthesize one
                // with approved_at just before rejected_at, so it reads as "granted
                // then revoked" without breaking the approved_at <> rejected_at check.
                $this->pdo->prepare(
                    "INSERT INTO teacher_specialisation_requests (teacher_id, approved_at, rejected_at, decided_by_id)
                     VALUES (:tid, NOW() - INTERVAL '1 second', NOW(), :admin)
                     ON CONFLICT (teacher_id) DO UPDATE
                     SET rejected_at = GREATEST(NOW(), teacher_specialisation_requests.approved_at + INTERVAL '1 second'),
                         approved_at = COALESCE(teacher_specialisation_requests.approved_at, NOW() - INTERVAL '1 second'),
                         decided_by_id = EXCLUDED.decided_by_id"
                )->execute(['tid' => $teacherId, 'admin' => $adminId]);
            }

            $this->pdo->commit();

            return $changed;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Re-habilitates a revoked teacher: sets is_specialised and stamps
     * approved_at again (keeping rejected_at as history). Returns whether
     * a revoked teacher was actually re-habilitated.
     */
    public function reauthorize(int $teacherId, int $adminId): bool
    {
        $this->pdo->beginTransaction();
        try {
            // approved_at must end up strictly after rejected_at so the row reads
            // as habilitated again, even if NOW() equals the revocation instant.
            $stmt = $this->pdo->prepare(
                "UPDATE teacher_specialisation_requests
                 SET approved_at = GREATEST(NOW(), rejected_at + INTERVAL '1 second'), decided_by_id = :admin
                 WHERE teacher_id = :tid
                   AND " . self::REVOKED_PREDICATE_BARE
            );
            $stmt->execute(['admin' => $adminId, 'tid' => $teacherId]);
            $reauthorized = $stmt->rowCount() > 0;

            if ($reauthorized) {
                $this->pdo->prepare('UPDATE teachers SET is_specialised = TRUE WHERE id = :tid')
                    ->execute(['tid' => $teacherId]);
            }

            $this->pdo->commit();

            return $reauthorized;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
