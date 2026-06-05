<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for `researcher_authorizations`.
 */
class ResearcherAuthorizationRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Pending requests for a department, joined with researcher identity and lab.
     *
     * @return list<array<string, mixed>>
     */
    public function findPendingByDepartment(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.researcher_id,
                    ra.request,
                    u.first_name,
                    u.last_name,
                    u.email,
                    l.code AS lab_code,
                    l.name AS lab_name
             FROM researcher_authorizations ra
             JOIN researchers r ON r.id = ra.researcher_id
             JOIN users u ON u.id = r.id
             JOIN laboratories l ON l.id = r.laboratory_id
             WHERE ra.department_id = :dept
               AND ra.authorized_at IS NULL
               AND ra.rejected_at IS NULL
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /** The department a pending request targets, or null if no pending row. */
    public function findPendingDepartmentId(int $researcherId, int $departmentId): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT department_id FROM researcher_authorizations
             WHERE researcher_id = :rid AND department_id = :dept
               AND authorized_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['rid' => $researcherId, 'dept' => $departmentId]);
        $value = $stmt->fetchColumn();

        return $value === false ? null : (int) $value;
    }

    /** Approves a pending request: stamps authorized_at and the deciding admin. */
    public function approve(int $researcherId, int $departmentId, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE researcher_authorizations
             SET authorized_at = NOW(), authorized_by_id = :admin
             WHERE researcher_id = :rid AND department_id = :dept
               AND authorized_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['admin' => $adminId, 'rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->rowCount();
    }

    /** Rejects a pending request: stamps rejected_at and the deciding admin. */
    public function reject(int $researcherId, int $departmentId, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE researcher_authorizations
             SET rejected_at = NOW(), authorized_by_id = :admin
             WHERE researcher_id = :rid AND department_id = :dept
               AND authorized_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['admin' => $adminId, 'rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->rowCount();
    }
}
