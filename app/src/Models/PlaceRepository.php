<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for the `places` table and their `departments`.
 *
 * Feeds the two cascading selects on the registration form: the first lists
 * the places, the second lists the departments of the chosen place (loaded
 * over AJAX). Holds every SQL statement that flow needs.
 */
class PlaceRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns every place, for the first registration select.
     *
     * @return list<array{id:int, name:string}>
     */
    public function all(): array
    {
        $stmt = $this->pdo->query('SELECT id, name FROM places ORDER BY name');

        return $stmt->fetchAll();
    }

    /**
     * Returns the active departments of a given place, for the second
     * (dependent) registration select.
     *
     * @return list<array{id:int, name:string}>
     */
    public function departmentsByPlace(int $placeId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, name
             FROM departments
             WHERE place_id = :place_id AND is_active = TRUE
             ORDER BY name'
        );
        $stmt->execute(['place_id' => $placeId]);

        return $stmt->fetchAll();
    }

    /**
     * The department's name with its place, or null if it does not exist.
     *
     * @return array{name:string, place_name:string}|null
     */
    public function departmentWithPlace(int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT d.name, p.name AS place_name
             FROM departments d
             JOIN places p ON p.id = d.place_id
             WHERE d.id = :id'
        );
        $stmt->execute(['id' => $departmentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Tells whether the department exists, is active, and belongs to the
     * given place. Used to validate the submitted pair server-side, since
     * the dependent select is filled by client-side JS we cannot trust.
     */
    public function departmentBelongsToPlace(int $departmentId, int $placeId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM departments
             WHERE id = :department_id AND place_id = :place_id AND is_active = TRUE'
        );
        $stmt->execute([
            'department_id' => $departmentId,
            'place_id'      => $placeId,
        ]);

        return $stmt->fetch() !== false;
    }
}
