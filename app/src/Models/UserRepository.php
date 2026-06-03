<?php

declare(strict_types=1);

namespace Models;

use PDO;
use Throwable;

/**
 * Data access for the `users` table
 *
 * Holds every SQL statement the authentication flow needs; AuthService
 * orchestrates the business logic and delegates all persistence here.
 */
class UserRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Returns the full user row for a given email, or null when none match.
     *
     * @return array<string, mixed>|null
     */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, first_name, last_name, is_active
             FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Tells whether an account already exists for the given email.
     */
    public function emailExists(string $email): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);

        return $stmt->fetch() !== false;
    }

    /**
     * Stamps the user's last successful login at the current time.
     */
    public function touchLastLogin(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $userId]);
    }

    /**
     * Tells whether the user owns the given role, i.e. has a row in the
     * matching specialisation table. The table name is whitelisted by the
     * caller (teachers / students) — never pass user input here.
     */
    public function hasRole(int $userId, string $table): bool
    {
        $stmt = $this->pdo->prepare("SELECT 1 FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $userId]);

        return $stmt->fetch() !== false;
    }

    /**
     * Creates a user and its role specialisation row in a single
     * transaction, so a half-created account can never linger. Returns the
     * new user id.
     *
     * @param array{
     *     email: string,
     *     password_hash: string,
     *     first_name: string,
     *     last_name: string,
     *     department_id: int,
     *     consent_version: string
     * } $user
     * @param 'teacher'|'student' $role
     *
     * @throws Throwable if any statement fails (the transaction is rolled back).
     */
    public function createUserWithRole(array $user, string $role): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, department_id, consent_at, consent_version)
                 VALUES (:email, :hash, :fn, :ln, :department_id, NOW(), :ver)
                 RETURNING id'
            );
            $stmt->execute([
                'email'         => $user['email'],
                'hash'          => $user['password_hash'],
                'fn'            => $user['first_name'],
                'ln'            => $user['last_name'],
                'department_id' => $user['department_id'],
                'ver'           => $user['consent_version'],
            ]);
            $userId = (int) $stmt->fetchColumn();

            // The role table only carries the FK to users.id; default values
            // are good for is_specialised (false) / title (null) /
            // student_number (null) — the user can fill them later.
            $table = $role === 'teacher' ? 'teachers' : 'students';
            $roleStmt = $this->pdo->prepare("INSERT INTO {$table} (id) VALUES (:id)");
            $roleStmt->execute(['id' => $userId]);

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    public function deactivate(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = FALSE WHERE id = :id AND is_active = TRUE'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->rowCount();
    }

    public function reactivate(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = TRUE WHERE id = :id AND is_active = FALSE'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->rowCount();
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, is_active FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }
}