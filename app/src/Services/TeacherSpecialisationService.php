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
}
