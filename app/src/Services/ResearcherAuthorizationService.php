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
    public function reject(int $researcherId, int $departmentId): array
    {
        if ($this->repo->findPendingDepartmentId($researcherId, $departmentId) === null) {
            return ['success' => false, 'error' => 'Cette demande est introuvable ou deja traitee.'];
        }

        $this->repo->reject($researcherId, $departmentId);

        return ['success' => true];
    }
}
