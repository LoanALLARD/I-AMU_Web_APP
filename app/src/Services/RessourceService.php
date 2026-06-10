<?php

declare(strict_types=1);

namespace Services;

use Models\ResourceRepository;
use Models\UserRepository;
use PDO;
use PDOException;

/**
 * Business logic for teacher-owned resources (courses).
 *
 * A resource belongs to a teacher (owner_id) and a department. Only the
 * owning teacher may create, edit, or delete it. Deletion is blocked at the
 * database level when sessions still reference the resource.
 */
class RessourceService
{
    private ResourceRepository $resources;
    private UserRepository $users;
    public function __construct(PDO $pdo)
    {
        $this->resources = new ResourceRepository($pdo);
        $this->users = new UserRepository($pdo);
    }

    /**
     * Returns all resources accessible to the teacher: owned + shared.
     * Each row carries an `is_owner` boolean flag.
     *
     * @return list<array<string, mixed>>
     */
    public function listForTeacher(int $teacherId): array
    {
        return $this->resources->findAllAccessibleByTeacher($teacherId);
    }


    /**
     * Returns teachers of the same department, excluding the owner.
     * Used to populate the assignment picker on the create/edit form.
     *
     * @return list<array{id:int, first_name:string, last_name:string, email:string}>
     */
    public function listDepartmentTeachers(int $departmentId, int $excludeTeacherId): array
    {
        $members = $this->users->listDepartmentMembers($departmentId);
 
        return array_values(array_filter(
            $members,
            static fn(array $m): bool =>
                $m['role'] === 'teacher' && (int) $m['id'] !== $excludeTeacherId
        ));
    }
 
    /**
     * Returns the ids of teachers currently assigned to a resource.
     *
     * @return list<int>
     */
    public function assignedTeacherIds(int $resourceId): array
    {
        return $this->resources->findAssignedTeacherIds($resourceId);
    }


    /**
     * Validates and creates a new resource.
     *
     * @param array<string, mixed> $data  POST-sourced fields
     * @param list<int> $assignedTeacherIds
     * @return array<string, mixed>       The inserted row
     * @throws \RuntimeException          On validation failure
     */
    public function create(array $data, int $teacherId, int $departmentId, array $assignedTeacherIds = []): array
    {
        $this->validate($data);

        $row = $this->resources->insert([
            'owner_id'      => $teacherId,
            'department_id' => $departmentId,
            'code'          => trim((string) $data['code']),
            'name'          => trim((string) $data['name']),
            'description'   => isset($data['description']) && trim((string) $data['description']) !== ''
                                ? trim((string) $data['description'])
                                : null,
            'semester'      => isset($data['semester']) && trim((string) $data['semester']) !== ''
                                ? trim((string) $data['semester'])
                                : null,
            'state'         => 'DRAFT',
        ]);

        $this->resources->syncAssignedTeachers(
            (int) $row['id'],
            $teacherId,
            $assignedTeacherIds
        );

        return $row;
    }



    /**
     * Validates and updates an existing resource the teacher owns.
     *
     * @param array<string, mixed> $data
     * @param list<int> $assignedTeacherIds
     * @throws \RuntimeException  On ownership mismatch or validation failure
     */
    public function update(int $resourceId, array $data, int $teacherId, array $assignedTeacherIds = []): void
    {
        $this->loadOwned($resourceId, $teacherId);
        $this->validate($data);

        $this->resources->update($resourceId, [
            'code'        => trim((string) $data['code']),
            'name'        => trim((string) $data['name']),
            'description' => isset($data['description']) && trim((string) $data['description']) !== ''
                                ? trim((string) $data['description'])
                                : null,
            'semester'    => isset($data['semester']) && trim((string) $data['semester']) !== ''
                                ? trim((string) $data['semester'])
                                : null,
            'state'       => in_array($data['state'] ?? '', ['DRAFT', 'PUBLISHED', 'ARCHIVED'], true)
                                ? $data['state']
                                : 'DRAFT',
        ]);

        $this->resources->syncAssignedTeachers(
            $resourceId,
            $teacherId,
            $assignedTeacherIds
        );
    }

    /**
     * Archives a resource the teacher owns (sets state to ARCHIVED).
     * Unlike deletion, this never fails due to linked sessions.
     *
     * @throws \RuntimeException  On ownership mismatch
     */
    public function archive(int $resourceId, int $teacherId): void
    {
        $this->loadOwned($resourceId, $teacherId);
        $this->resources->archive($resourceId);
    }

    /**
     * Restores an archived resource the teacher owns (sets state back to DRAFT).
     *
     * @throws \RuntimeException  On ownership mismatch
     */
    public function restore(int $resourceId, int $teacherId): void
    {
        $this->loadOwned($resourceId, $teacherId);
        $this->resources->restore($resourceId);
    }

    /**
     * Returns archived resources owned by the teacher.
     *
     * @return list<array<string, mixed>>
     */
    public function listArchivedForTeacher(int $teacherId): array
    {
        return $this->resources->findArchivedByOwner($teacherId);
    }

    /**
     * Returns a resource row only if it is owned by the given teacher.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException  On miss or ownership mismatch
     */
    public function loadOwned(int $resourceId, int $teacherId): array
    {
        $row = $this->resources->findById($resourceId);

        if ($row === null || (int) $row['owner_id'] !== $teacherId) {
            throw new \RuntimeException('Ressource introuvable ou inaccessible.');
        }

        return $row;
    }

    /**
     * Publishes a DRAFT resource the teacher owns (sets state to PUBLISHED).
     *
     * @throws \RuntimeException  On ownership mismatch or wrong state
     */
    public function publish(int $resourceId, int $teacherId): void
    {
        $row = $this->loadOwned($resourceId, $teacherId);

        if ($row['state'] === 'PUBLISHED') {
            throw new \RuntimeException('Cette ressource est déjà publiée.');
        }
        if ($row['state'] === 'ARCHIVED') {
            throw new \RuntimeException('Une ressource archivée ne peut pas être publiée directement. Restaurez-la d\'abord.');
        }

        $this->resources->publish($resourceId);
    }

    // ----------------------------------------------------------------
    // internals
    // ----------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     * @throws \RuntimeException
     */
    private function validate(array $data): void
    {
        $code = trim((string) ($data['code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));

        if ($code === '') {
            throw new \RuntimeException('Le code de la ressource est obligatoire.');
        }
        if (strlen($code) > 50) {
            throw new \RuntimeException('Le code ne peut pas dépasser 50 caractères.');
        }
        if ($name === '') {
            throw new \RuntimeException('Le nom de la ressource est obligatoire.');
        }
        if (strlen($name) > 50) {
            throw new \RuntimeException('Le nom ne peut pas dépasser 50 caractères.');
        }
        $semester = trim((string) ($data['semester'] ?? ''));
        if ($semester !== '' && strlen($semester) > 10) {
            throw new \RuntimeException('Le semestre ne peut pas dépasser 10 caractères.');
        }
    }

    /**
     * Returns true if the teacher owns the resource OR has been granted access
     * via teacher_resources.
     */
    public function isAccessibleByTeacher(int $resourceId, int $teacherId): bool
    {
        $rows = $this->resources->findAllAccessibleByTeacher($teacherId);

        foreach ($rows as $row) {
            if ((int) $row['id'] === $resourceId) {
                return true;
            }
        }

        return false;
    }
}