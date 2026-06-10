<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Read access for `resources` (a course a session hangs off). The owning
 * teacher is `resources.owner_id`.
 */
class ResourceRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * All resources a teacher can use: those they own (owner_id) UNION
     * those explicitly shared with them via teacher_resources.
     * The `is_owner` flag lets the controller gate edit/delete actions.
     *
     * @return list<array<string, mixed>>
     */
    public function findAllAccessibleByTeacher(int $teacherId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id,
                    r.department_id,
                    r.owner_id,
                    r.code,
                    r.name,
                    r.description,
                    r.semester,
                    r.state,
                    (r.owner_id = :tid1) AS is_owner
             FROM resources r
             WHERE (r.owner_id = :tid2
                OR EXISTS (
                    SELECT 1
                    FROM teacher_resources tr
                    WHERE tr.resource_id = r.id
                      AND tr.teacher_id  = :tid3
                ))
                AND r.state !='ARCHIVED'
             ORDER BY r.code"
        );
        $stmt->execute(['tid1' => $teacherId, 'tid2' => $teacherId, 'tid3' => $teacherId]);
 
        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
 
        return $rows;
    }


    /**
     * @return list<array<string, mixed>>
     */
    public function findAllByOwner(int $teacherId): array
    {
        // A teacher can attach a session to a resource they OWN or are a
        // responsible teacher of (teacher_resources) — same rule as
        // isAccessibleByTeacher(). The previous query only matched the shared
        // ones (the JOIN), so owners with no teacher_resources row got nothing.
        $stmt = $this->pdo->prepare(
            'SELECT r.id, r.department_id, r.owner_id, r.code, r.name, r.description, r.semester, r.state
               FROM resources r
              WHERE r.owner_id = :tid1
                 OR EXISTS (
                     SELECT 1 FROM teacher_resources tr
                      WHERE tr.resource_id = r.id AND tr.teacher_id = :tid2
                 )
              ORDER BY r.code'
        );
        $stmt->execute(['tid1' => $teacherId, 'tid2' => $teacherId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, owner_id, code, name, description, semester, state FROM resources WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    /**
     * Archived resources owned by the teacher.
     *
     * @return list<array<string, mixed>>
     */
    public function findArchivedByOwner(int $teacherId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.id,
                    r.department_id,
                    r.owner_id,
                    r.code,
                    r.name,
                    r.description,
                    r.semester,
                    r.state,
                    true AS is_owner
            FROM resources r
            WHERE r.owner_id = :tid
            AND r.state = 'ARCHIVED'
            ORDER BY r.code"
        );
        $stmt->execute(['tid' => $teacherId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Archives a resource (sets state to ARCHIVED).
     */
    public function archive(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE resources SET state = 'ARCHIVED' WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Restores an archived resource by resetting its state to DRAFT.
     */
    public function restore(int $id): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE resources SET state = 'DRAFT' WHERE id = :id"
        );
        $stmt->execute(['id' => $id]);
    }

    
    /**
     * Whether the teacher is attached to the resource via `teacher_resources`,
     * i.e. a co-teacher / read-only supervisor. This is distinct from being the
     * owner (`resources.owner_id`): ownership is checked separately.
     */
    public function isResourceTeacher(int $resourceId, int $teacherId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM teacher_resources WHERE resource_id = :rid AND teacher_id = :tid'
        );
        $stmt->execute(['rid' => $resourceId, 'tid' => $teacherId]);

        return $stmt->fetch() !== false;
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    public function getResourcesFromUserId(int $userId): ?array {
        $query = $this->pdo->prepare(
            'SELECT r.id, r.name from resources r join users u on r.id = u.department_id where u.id = :id'
        );
        $query->execute(['id' => $userId]);

        $result = $query->fetchAll(\PDO::FETCH_ASSOC);

        if ($result === false) {
            return null;
        }

        return $result;
    }

    /**
     * Inserts a new resource and returns the full row.
     *
     * @param array<string, mixed> $data  Keys: owner_id, department_id, code, name, description, semester, state
     * @return array<string, mixed>
     */
    public function insert(array $data): array
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO resources (owner_id, department_id, code, name, description, semester, state)
             VALUES (:owner_id, :department_id, :code, :name, :description, :semester, :state)
             RETURNING id, owner_id, department_id, code, name, description, semester, state'
        );
        $stmt->execute([
            'owner_id'      => $data['owner_id'],
            'department_id' => $data['department_id'],
            'code'          => $data['code'],
            'name'          => $data['name'],
            'description'   => $data['description'] ?? null,
            'semester'      => $data['semester']    ?? null,
            'state'         => $data['state']       ?? 'DRAFT',
        ]);
 

        $idGenere = $this->pdo->lastInsertId();

        if (!$idGenere) {
            throw new \RuntimeException('Failed to insert resource.');
        }

        $statement = $this->pdo->prepare('INSERT INTO teacher_resources (resource_id, teacher_id) VALUES (:rid, :tid)');
        $statement->execute([
            'rid' => $idGenere,
            'tid' => $data['owner_id'],
        ]);

        $querySelect = $this->pdo->prepare('SELECT * FROM resources WHERE id = :id');
        $querySelect->execute(['id' => $idGenere]);

        /** @var array<string, mixed>|false $result */
        $result = $querySelect->fetch();
        if ($result === false) {
            throw new \RuntimeException('Inserted resource could not be reloaded.');
        }

        return $result;
    }
 
    /**
     * Updates an existing resource. Only the fields editable post-creation
     * are accepted: code, name, description, semester, state.
     *
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE resources
             SET code        = :code,
                 name        = :name,
                 description = :description,
                 semester    = :semester,
                 state       = :state
             WHERE id = :id'
        );
        $stmt->execute([
            'id'          => $id,
            'code'        => $data['code'],
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'semester'    => $data['semester']    ?? null,
            'state'       => $data['state']       ?? 'DRAFT',
        ]);
    }
 

    /**
     * Returns the ids of all teachers currently assigned to a resource
     * via teacher_resources (excludes the owner).
     *
     * @return list<int>
     */
    public function findAssignedTeacherIds(int $resourceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT teacher_id FROM teacher_resources WHERE resource_id = :rid'
        );
        $stmt->execute(['rid' => $resourceId]);
 
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
 
    /**
     * Replaces the full set of assigned teachers for a resource in one
     * transaction: deletes all existing rows then re-inserts the new set.
     * Passing an empty array removes all assignments.
     *
     * The owner's id is silently excluded from the insert so it never
     * appears in teacher_resources (they already have access via owner_id).
     *
     * @param list<int> $teacherIds
     */
    public function syncAssignedTeachers(int $resourceId, int $ownerId, array $teacherIds): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO teacher_resources (resource_id, teacher_id)
            VALUES (:rid, :tid)'
        );

        foreach ($teacherIds as $teacherId) {
            $stmt->execute([
                'rid' => $resourceId,
                'tid' => (int) $teacherId,
            ]);
        }
    }

    /**
     * Returns true if the teacher owns the resource OR appears in teacher_resources for it.
     */
    public function isAccessibleByTeacher(int $resourceId, int $teacherId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM resources r
            WHERE r.id = :rid
                AND (
                    r.owner_id = :tid1
                    OR EXISTS (
                        SELECT 1 FROM teacher_resources tr
                        WHERE tr.resource_id = r.id
                        AND tr.teacher_id  = :tid2
                    )
                )
            LIMIT 1'
        );
        $stmt->execute(['rid' => $resourceId, 'tid1' => $teacherId, 'tid2' => $teacherId]);

        return (bool) $stmt->fetchColumn();
    }

}
