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

    // ----------------------------------------------------------------
    // Super admin panel: places & departments CRUD
    // ----------------------------------------------------------------

    /**
     * Every place with its full address, newest first, for the admin table.
     *
     * @return list<array{id:int, name:string, address:?string, city:?string, zip_code:?string}>
     */
    public function findAllPlaces(): array
    {
        $stmt = $this->pdo->query(
            'SELECT id, name, address, city, zip_code FROM places ORDER BY id DESC'
        );

        return array_map(
            static fn (array $row): array => [
                'id'       => (int) $row['id'],
                'name'     => (string) $row['name'],
                'address'  => $row['address'] !== null ? (string) $row['address'] : null,
                'city'     => $row['city'] !== null ? (string) $row['city'] : null,
                'zip_code' => $row['zip_code'] !== null ? (string) $row['zip_code'] : null,
            ],
            $stmt->fetchAll()
        );
    }

    /** Inserts a place and returns its id. */
    public function addPlace(string $name, ?string $address, ?string $city, ?string $zipCode): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO places (name, address, city, zip_code)
             VALUES (:name, :address, :city, :zip_code)
             RETURNING id'
        );
        $stmt->execute([
            'name'     => $name,
            'address'  => $address,
            'city'     => $city,
            'zip_code' => $zipCode,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function placeExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM places WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /** Number of departments attached to a place (a place with any cannot be deleted). */
    public function countDepartmentsOfPlace(int $placeId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM departments WHERE place_id = :place_id'
        );
        $stmt->execute(['place_id' => $placeId]);

        return (int) $stmt->fetchColumn();
    }

    public function deletePlace(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM places WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    /**
     * Every department with its owning place, newest first, for the admin table.
     *
     * @return list<array{id:int, place_id:int, place_name:string, name:string, description:?string, is_active:bool}>
     */
    public function findAllDepartments(): array
    {
        $stmt = $this->pdo->query(
            'SELECT d.id, d.place_id, p.name AS place_name, d.name, d.description, d.is_active
             FROM departments d
             JOIN places p ON p.id = d.place_id
             ORDER BY d.id DESC'
        );

        return array_map(
            static fn (array $row): array => [
                'id'          => (int) $row['id'],
                'place_id'    => (int) $row['place_id'],
                'place_name'  => (string) $row['place_name'],
                'name'        => (string) $row['name'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'is_active'   => (bool) $row['is_active'],
            ],
            $stmt->fetchAll()
        );
    }

    /** True when a department of that name already exists in the place (uq constraint). */
    public function departmentNameExistsInPlace(int $placeId, string $name): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM departments WHERE place_id = :place_id AND name = :name'
        );
        $stmt->execute(['place_id' => $placeId, 'name' => $name]);

        return $stmt->fetchColumn() !== false;
    }

    /** Inserts a department and returns its id. */
    public function addDepartment(int $placeId, string $name, ?string $description): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO departments (place_id, name, description)
             VALUES (:place_id, :name, :description)
             RETURNING id'
        );
        $stmt->execute([
            'place_id'    => $placeId,
            'name'        => $name,
            'description' => $description,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function departmentExists(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM departments WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    /** Enables or disables a department (the panel's soft "delete"). */
    public function setDepartmentActive(int $id, bool $isActive): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE departments SET is_active = :active WHERE id = :id'
        );
        // Bind as bool: in execute() PDO sends false as '', which PG rejects for BOOLEAN.
        $stmt->bindValue('active', $isActive, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
