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

    /** Whether the teacher currently has an undecided request. */
    public function hasPending(int $teacherId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM teacher_specialisation_requests
             WHERE teacher_id = :tid
               AND approved_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['tid' => $teacherId]);

        return $stmt->fetch() !== false;
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
}
