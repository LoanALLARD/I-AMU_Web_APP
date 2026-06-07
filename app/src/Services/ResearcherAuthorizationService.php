<?php

declare(strict_types=1);

namespace Services;

use Models\PlaceRepository;
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
    private PlaceRepository $places;

    public function __construct(PDO $pdo)
    {
        $this->repo   = new ResearcherAuthorizationRepository($pdo);
        $this->places = new PlaceRepository($pdo);
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

    // ----------------------------------------------------------------
    // Researcher side: a researcher requests access to a department.
    // ----------------------------------------------------------------

    /**
     * Files a researcher's access request for a department. The department must
     * exist, be active, and belong to the given place (re-checked server-side
     * since the dependent select is JS-filled). A repeat on a rejected/revoked
     * pair resets it to pending — the PK guarantees one request per department.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function requestAccess(int $researcherId, int $placeId, int $departmentId, string $request): array
    {
        if ($placeId === 0 || $departmentId === 0) {
            return ['success' => false, 'error' => 'Veuillez choisir un lieu et un departement.'];
        }
        if (!$this->places->departmentBelongsToPlace($departmentId, $placeId)) {
            return ['success' => false, 'error' => 'Le departement selectionne est invalide.'];
        }
        if ($this->repo->isAuthorized($researcherId, $departmentId)) {
            return ['success' => false, 'error' => 'Vous avez deja un acces actif a ce departement.'];
        }
        if ($this->repo->findPendingDepartmentId($researcherId, $departmentId) !== null) {
            return ['success' => false, 'error' => 'Une demande est deja en attente pour ce departement.'];
        }

        $request = trim($request);
        $this->repo->submitRequest($researcherId, $departmentId, $request === '' ? null : $request);

        return ['success' => true];
    }

    /**
     * The researcher's own requests, each tagged with a status derived from the
     * timestamps: 'pending', 'authorized', 'rejected' or 'revoked'.
     *
     * @return list<array<string, mixed>>
     */
    public function listForResearcher(int $researcherId): array
    {
        return array_map(
            static function (array $row): array {
                $row['status'] = self::deriveStatus(
                    $row['authorized_at'] ?? null,
                    $row['rejected_at'] ?? null
                );
                return $row;
            },
            $this->repo->findByResearcher($researcherId)
        );
    }

    /**
     * Maps the (authorized_at, rejected_at) pair to a status label, mirroring
     * the repository's SQL predicates: the most recent decision wins.
     */
    private static function deriveStatus(?string $authorizedAt, ?string $rejectedAt): string
    {
        if ($authorizedAt === null && $rejectedAt === null) {
            return 'pending';
        }
        if ($rejectedAt === null) {
            return 'authorized';
        }
        if ($authorizedAt === null) {
            return 'rejected';
        }

        return $authorizedAt > $rejectedAt ? 'authorized' : 'revoked';
    }
}
