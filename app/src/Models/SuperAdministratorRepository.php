<?php

declare(strict_types=1);

namespace Models;

use PDO;

/**
 * Data access for the `super_administrators` table.
 *
 * Deliberately separate from UserRepository: a super admin is NOT a user
 * (see docs/specs/05-admin-research.md A.0). Keeping the highest-privilege
 * accounts in their own table isolates them if `users` is ever compromised.
 * Holds the SQL the super admin authentication flow needs; the service
 * (SuperAdminAuthService) drives the business logic.
 */
class SuperAdministratorRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns the full super admin row for a given email, or null.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, first_name, last_name
             FROM super_administrators WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, first_name, last_name
             FROM super_administrators WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Stamps the super admin's last successful login at the current time.
     */
    public function touchLastLogin(int $id): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE super_administrators SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute(['id' => $id]);
    }

    /**
     * Inserts a super admin and returns its id. The caller hashes the
     * password (the repository only stores what it is given).
     */
    public function create(
        string $email,
        string $passwordHash,
        string $firstName,
        string $lastName
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO super_administrators (email, password_hash, first_name, last_name)
             VALUES (:email, :hash, :fn, :ln)
             RETURNING id'
        );
        $stmt->execute([
            'email' => $email,
            'hash'  => $passwordHash,
            'fn'    => $firstName,
            'ln'    => $lastName,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function updatePassword(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $query = $this->pdo->prepare(
            'UPDATE super_administrators SET password_hash = :password WHERE id = :id'
        );

        return $query->execute([
            'password' => $hash,
            'id'       => $id
        ]);
    }

    public function updateFirstName(int $id, string $firstName): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE super_administrators SET first_name = :first_name WHERE id = :id'
        );

        return $query->execute([
            'first_name' => $firstName,
            'id'         => $id
        ]);
    }

    public function updateLastName(int $id, string $lastName): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE super_administrators SET last_name = :last_name WHERE id = :id'
        );

        return $query->execute([
            'last_name' => $lastName,
            'id'        => $id
        ]);
    }

    public function updateEmail(int $id, string $email): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE super_administrators SET email = :email WHERE id = :id'
        );

        return $query->execute([
            'email' => $email,
            'id'    => $id
        ]);
    }

}
