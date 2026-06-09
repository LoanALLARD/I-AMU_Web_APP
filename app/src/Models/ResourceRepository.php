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
     * @return list<array<string, mixed>>
     */
    public function findAllByOwner(int $teacherId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, owner_id, code, name, state FROM resources WHERE owner_id = :tid ORDER BY code');
        $stmt->execute(['tid' => $teacherId]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, owner_id, code, name, state FROM resources WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }
}
