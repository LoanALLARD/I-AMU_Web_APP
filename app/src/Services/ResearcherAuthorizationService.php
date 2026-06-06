<?php

declare(strict_types=1);

namespace Services;

use Models\ResearcherAuthorizationRepository;
use PDO;

/**
 * Department-scoped handling of researcher access requests. Every operation
 * is bounded by the caller's department id, passed by the controller from
 * its own scope, so an admin can only act on its department's requests.
 */
final class ResearcherAuthorizationService
{
    private ResearcherAuthorizationRepository $repo;

    public function __construct(PDO $pdo)
    {
        $this->repo = new ResearcherAuthorizationRepository($pdo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPending(int $departmentId): array
    {
        return $this->repo->findPendingByDepartment($departmentId);
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function approve(int $researcherId, int $departmentId, int $adminId): array
    {
        if ($this->repo->findPendingDepartmentId($researcherId, $departmentId) === null) {
            return ['success' => false, 'error' => 'Cette demande est introuvable ou deja traitee.'];
        }

        $this->repo->approve($researcherId, $departmentId, $adminId);

        return ['success' => true];
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function reject(int $researcherId, int $departmentId, int $adminId): array
    {
        if ($this->repo->findPendingDepartmentId($researcherId, $departmentId) === null) {
            return ['success' => false, 'error' => 'Cette demande est introuvable ou deja traitee.'];
        }

        $this->repo->reject($researcherId, $departmentId, $adminId);

        return ['success' => true];
    }

    /**
     * Researchers whose access was granted then revoked on the department.
     *
     * @return list<array<string, mixed>>
     */
    public function listRevoked(int $departmentId): array
    {
        return $this->repo->findRevokedByDepartment($departmentId);
    }

    /**
     * Identity row for a single researcher, to re-render its table row.
     *
     * @return array<string, mixed>|null
     */
    public function findRow(int $researcherId, int $departmentId): ?array
    {
        return $this->repo->findRow($researcherId, $departmentId);
    }

    /**
     * Revokes a researcher's already-granted access to the department. Leaves
     * the user account untouched (global is_active is the super admin's lever).
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function revoke(int $researcherId, int $departmentId, int $adminId): array
    {
        if (!$this->repo->isAuthorized($researcherId, $departmentId)) {
            return ['success' => false, 'error' => 'Ce chercheur n\'a pas d\'acces actif a votre departement.'];
        }

        $this->repo->revoke($researcherId, $departmentId, $adminId);

        return ['success' => true];
    }

    /**
     * Re-authorizes a researcher whose access was previously revoked. Exploits
     * the kept history: no new request from the researcher is needed.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function reauthorize(int $researcherId, int $departmentId, int $adminId): array
    {
        if (!$this->repo->isRevoked($researcherId, $departmentId)) {
            return ['success' => false, 'error' => 'Ce chercheur n\'a pas d\'acces revoque a reactiver.'];
        }

        $this->repo->reauthorize($researcherId, $departmentId, $adminId);

        return ['success' => true];
    }
}
