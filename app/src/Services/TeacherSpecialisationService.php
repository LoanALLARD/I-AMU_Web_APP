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
     *
     * @return array{success: true} | array{success: false, error: string}
     */
    public function requestSpecialisation(int $teacherId, string $request): array
    {
        if ($this->users->isTeacherSpecialized($teacherId)) {
            return ['success' => false, 'error' => 'Vous êtes déjà habilité.'];
        }
        if ($this->repo->hasPending($teacherId)) {
            return ['success' => false, 'error' => 'Une demande est déjà en attente.'];
        }

        $request = trim($request);
        $this->repo->submitRequest($teacherId, $request === '' ? null : $request);

        return ['success' => true];
    }

    /** Whether the teacher has an undecided habilitation request. */
    public function hasPendingRequest(int $teacherId): bool
    {
        return $this->repo->hasPending($teacherId);
    }
}
