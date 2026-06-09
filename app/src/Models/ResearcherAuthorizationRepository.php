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

    /**
     * SQL predicate: access is currently granted. Both timestamps may be set
     * (revoked then re-granted); the most recent decision wins.
     */
    private const ACTIVE_PREDICATE =
        'authorized_at IS NOT NULL AND (rejected_at IS NULL OR authorized_at > rejected_at)';

    /**
     * SQL predicate: access was granted then revoked. rejected_at is the most
     * recent decision, so the researcher no longer has access.
     */
    private const REVOKED_PREDICATE =
        'rejected_at IS NOT NULL AND authorized_at IS NOT NULL AND rejected_at > authorized_at';

    /** Whether the researcher currently holds an active grant on the department. */
    public function isAuthorized(int $researcherId, int $departmentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM researcher_authorizations
             WHERE researcher_id = :rid AND department_id = :dept
               AND ' . self::ACTIVE_PREDICATE
        );
        $stmt->execute(['rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->fetch() !== false;
    }

    /** Whether the researcher's access was granted then revoked on the department. */
    public function isRevoked(int $researcherId, int $departmentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM researcher_authorizations
             WHERE researcher_id = :rid AND department_id = :dept
               AND ' . self::REVOKED_PREDICATE
        );
        $stmt->execute(['rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Researchers whose access was granted then revoked, joined with their lab.
     * They no longer have access but the history (both timestamps) is kept, so
     * a department admin can re-authorize them without a new request.
     *
     * @return list<array<string, mixed>>
     */
    public function findRevokedByDepartment(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.researcher_id,
                    ra.rejected_at,
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
               AND ' . self::REVOKED_PREDICATE . '
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Identity row for a single researcher on a department, for re-rendering
     * its table row after an action. State-independent (the caller knows which
     * mode to render); scoped to the department so it cannot leak others.
     *
     * @return array{researcher_id:int, first_name:string, last_name:string, email:string, lab_code:string, lab_name:string}|null
     */
    public function findRow(int $researcherId, int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.researcher_id,
                    u.first_name,
                    u.last_name,
                    u.email,
                    l.code AS lab_code,
                    l.name AS lab_name
             FROM researcher_authorizations ra
             JOIN researchers r ON r.id = ra.researcher_id
             JOIN users u ON u.id = r.id
             JOIN laboratories l ON l.id = r.laboratory_id
             WHERE ra.researcher_id = :rid AND ra.department_id = :dept'
        );
        $stmt->execute(['rid' => $researcherId, 'dept' => $departmentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Revokes an already-granted access: stamps rejected_at (keeping
     * authorized_at, so the grant history survives). The researcher loses
     * access to THIS department only; the user account (users.is_active) is
     * untouched — that is the super admin's lever, not a department admin's.
     */
    public function revoke(int $researcherId, int $departmentId, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE researcher_authorizations
             SET rejected_at = NOW(), authorized_by_id = :admin
             WHERE researcher_id = :rid AND department_id = :dept
               AND ' . self::ACTIVE_PREDICATE
        );
        $stmt->execute(['admin' => $adminId, 'rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->rowCount();
    }

    /**
     * Re-authorizes a revoked access: stamps authorized_at again (keeping
     * rejected_at, so the revocation stays in the history). Access is granted
     * because authorized_at now post-dates rejected_at.
     */
    public function reauthorize(int $researcherId, int $departmentId, int $adminId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE researcher_authorizations
             SET authorized_at = NOW(), authorized_by_id = :admin
             WHERE researcher_id = :rid AND department_id = :dept
               AND ' . self::REVOKED_PREDICATE
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

    /** Files an access request; a repeat reuses the row (PK) and resets it to pending. */
    public function submitRequest(int $researcherId, int $departmentId, ?string $request): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO researcher_authorizations (researcher_id, department_id, request)
             VALUES (:rid, :dept, :request)
             ON CONFLICT (researcher_id, department_id) DO UPDATE
             SET request = EXCLUDED.request,
                 authorized_at = NULL,
                 rejected_at = NULL,
                 authorized_by_id = NULL'
        );
        $stmt->execute([
            'rid'     => $researcherId,
            'dept'    => $departmentId,
            'request' => $request,
        ]);
    }

    /**
     * Deletes a still-pending request, scoped to the researcher (anti-IDOR).
     * A decided request is kept as history. Returns rows deleted (0 = none).
     */
    public function cancelPending(int $researcherId, int $departmentId): int
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM researcher_authorizations
             WHERE researcher_id = :rid AND department_id = :dept
               AND authorized_at IS NULL AND rejected_at IS NULL'
        );
        $stmt->execute(['rid' => $researcherId, 'dept' => $departmentId]);

        return $stmt->rowCount();
    }

    /**
     * A researcher's requests with department, place and raw timestamps.
     *
     * @return list<array<string, mixed>>
     */
    public function findByResearcher(int $researcherId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.department_id,
                    ra.request,
                    ra.authorized_at,
                    ra.rejected_at,
                    d.name AS department_name,
                    p.name AS place_name
             FROM researcher_authorizations ra
             JOIN departments d ON d.id = ra.department_id
             JOIN places p ON p.id = d.place_id
             WHERE ra.researcher_id = :rid
             ORDER BY p.name, d.name'
        );
        $stmt->execute(['rid' => $researcherId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }
}
