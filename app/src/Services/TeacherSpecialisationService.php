<?php

declare(strict_types=1);

namespace Services;

use Models\TeacherSpecialisationRequestRepository;
use Models\UserRepository;
use PDO;

/** Teacher-side handling of habilitation (`teachers.is_specialised`) requests. */
final class TeacherSpecialisationService
{
    private TeacherSpecialisationRequestRepository $repo;
    private UserRepository $users;

    public function __construct(PDO $pdo)
    {
        $this->repo  = new TeacherSpecialisationRequestRepository($pdo);
        $this->users = new UserRepository($pdo);
    }

    /**
     * Files a habilitation request, refused if already habilitated or pending.
     * A rejected request may be re-filed (the row is reset to pending).
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function requestSpecialisation(int $teacherId, string $request): array
    {
        if ($this->users->isTeacherSpecialized($teacherId)) {
            return ['success' => false, 'error' => 'Vous êtes déjà habilité.'];
        }
        if ($this->requestStatus($teacherId) === 'pending') {
            return ['success' => false, 'error' => 'Une demande est déjà en attente.'];
        }

        $request = trim($request);
        $this->repo->submitRequest($teacherId, $request === '' ? null : $request);

        return ['success' => true];
    }

    /**
     * The teacher's request status: 'none', 'pending', 'rejected' or 'approved'.
     */
    public function requestStatus(int $teacherId): string
    {
        $decision = $this->repo->findDecision($teacherId);
        if ($decision === null) {
            return 'none';
        }
        if ($decision['approved_at'] === null && $decision['rejected_at'] === null) {
            return 'pending';
        }

        return $decision['rejected_at'] !== null ? 'rejected' : 'approved';
    }

    // ----------------------------------------------------------------
    // Department-admin side: review pending requests, scoped to a department.
    // ----------------------------------------------------------------

    /**
     * Pending habilitation requests of a department's teachers.
     *
     * @return list<array<string, mixed>>
     */
    public function listPending(int $departmentId): array
    {
        return $this->repo->findPendingByDepartment($departmentId);
    }

    /**
     * Approves a pending request and habilitates the teacher.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function approve(int $teacherId, int $departmentId, int $adminId): array
    {
        if (!$this->users->isDepartmentMember($teacherId, $departmentId)) {
            return ['success' => false, 'error' => 'Enseignant introuvable dans votre département.'];
        }
        if (!$this->repo->approve($teacherId, $adminId)) {
            return ['success' => false, 'error' => 'Cette demande est introuvable ou déjà traitée.'];
        }

        return ['success' => true];
    }

    /**
     * Rejects a pending request.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function reject(int $teacherId, int $departmentId, int $adminId): array
    {
        if (!$this->users->isDepartmentMember($teacherId, $departmentId)) {
            return ['success' => false, 'error' => 'Enseignant introuvable dans votre département.'];
        }
        if ($this->repo->reject($teacherId, $adminId) === 0) {
            return ['success' => false, 'error' => 'Cette demande est introuvable ou déjà traitée.'];
        }

        return ['success' => true];
    }

    /**
     * Habilitated teachers of the department.
     *
     * @return list<array<string, mixed>>
     */
    public function listHabilitated(int $departmentId): array
    {
        return $this->repo->findHabilitatedByDepartment($departmentId);
    }

    /**
     * Teachers whose habilitation was granted then revoked.
     *
     * @return list<array<string, mixed>>
     */
    public function listRevoked(int $departmentId): array
    {
        return $this->repo->findRevokedByDepartment($departmentId);
    }

    /**
     * Identity row for a single teacher, to re-render its table row.
     *
     * @return array<string, mixed>|null
     */
    public function findRow(int $teacherId, int $departmentId): ?array
    {
        return $this->repo->findRow($teacherId, $departmentId);
    }

    /**
     * Revokes a teacher's habilitation.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function revoke(int $teacherId, int $departmentId, int $adminId): array
    {
        if (!$this->users->isDepartmentMember($teacherId, $departmentId)) {
            return ['success' => false, 'error' => 'Enseignant introuvable dans votre département.'];
        }
        if (!$this->repo->revoke($teacherId, $adminId)) {
            return ['success' => false, 'error' => 'Cet enseignant n\'est pas habilité.'];
        }

        return ['success' => true];
    }

    /**
     * Re-habilitates a previously revoked teacher.
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function reauthorize(int $teacherId, int $departmentId, int $adminId): array
    {
        if (!$this->users->isDepartmentMember($teacherId, $departmentId)) {
            return ['success' => false, 'error' => 'Enseignant introuvable dans votre département.'];
        }
        if (!$this->repo->reauthorize($teacherId, $adminId)) {
            return ['success' => false, 'error' => 'Cet enseignant n\'a pas d\'habilitation révoquée à rétablir.'];
        }

        return ['success' => true];
    }
}
