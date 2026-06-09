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
            'SELECT id, email, password_hash, first_name, last_name, is_active, theme, email_verified_at, department_id
             FROM users WHERE email = :email'
        );
        $stmt->execute(['email' => $email]);

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    /**
     * Persists the user's UI theme preference. AUTO follows the OS setting.
     *
     * @param 'LIGHT'|'DARK'|'AUTO' $theme
     */
    public function updateTheme(int $userId, string $theme): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET theme = CAST(:theme AS theme_type) WHERE id = :id'
        );
        $stmt->execute(['theme' => $theme, 'id' => $userId]);
    }

    /**
     * Updates the user's display name (first + last).
     */
    public function updateName(int $userId, string $firstName, string $lastName): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET first_name = :fn, last_name = :ln WHERE id = :id'
        );
        $stmt->execute(['fn' => $firstName, 'ln' => $lastName, 'id' => $userId]);
    }

    /**
     * Replaces the user's password hash. The caller hashes the new password
     * (the repository only stores what it is given).
     */
    public function updatePassword(int $userId, string $passwordHash): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET password_hash = :hash WHERE id = :id'
        );
        $stmt->execute(['hash' => $passwordHash, 'id' => $userId]);
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
     * A researcher carries no department (department_id is NULL) and is tied
     * to a laboratory instead: pass the resolved laboratory id. Students and
     * teachers carry a department_id and no laboratory.
     *
     * @param array{
     *     email: string,
     *     password_hash: string,
     *     first_name: string,
     *     last_name: string,
     *     department_id: int|null,
     *     consent_version: string,
     *     laboratory_id?: int|null
     * } $user
     * @param 'teacher'|'student'|'researcher' $role
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

            if ($role === 'researcher') {
                $roleStmt = $this->pdo->prepare(
                    'INSERT INTO researchers (id, laboratory_id) VALUES (:id, :lab)'
                );
                $roleStmt->execute(['id' => $userId, 'lab' => $user['laboratory_id'] ?? null]);
            } else {
                $table = $role === 'teacher' ? 'teachers' : 'students';
                $roleStmt = $this->pdo->prepare("INSERT INTO {$table} (id) VALUES (:id)");
                $roleStmt->execute(['id' => $userId]);
            }

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    /**
     * Students and teachers attached to a department, role derived from the
     * inheritance tables. Department admins are excluded on purpose.
     *
     * @return list<array{id:int, email:string, first_name:string, last_name:string, is_active:bool, role:string}>
     */
    public function listDepartmentMembers(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active,
                    CASE WHEN t.id IS NOT NULL THEN 'teacher' ELSE 'student' END AS role
             FROM users u
             LEFT JOIN teachers t ON t.id = u.id
             LEFT JOIN students s ON s.id = u.id
             WHERE u.department_id = :dept
               AND (t.id IS NOT NULL OR s.id IS NOT NULL)
             ORDER BY u.last_name, u.first_name"
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array{id:int, email:string, first_name:string, last_name:string, is_active:bool, role:string}> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * A single member row (same projection as listDepartmentMembers), to
     * re-render its table row after a state change. Scoped to the department so
     * it cannot read a member of another one.
     *
     * @return array{id:int, email:string, first_name:string, last_name:string, is_active:bool, role:string}|null
     */
    public function findMemberRow(int $userId, int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active,
                    CASE WHEN t.id IS NOT NULL THEN 'teacher' ELSE 'student' END AS role
             FROM users u
             LEFT JOIN teachers t ON t.id = u.id
             LEFT JOIN students s ON s.id = u.id
             WHERE u.id = :id AND u.department_id = :dept
               AND (t.id IS NOT NULL OR s.id IS NOT NULL)"
        );
        $stmt->execute(['id' => $userId, 'dept' => $departmentId]);
        $row = $stmt->fetch();

        return $row === false ? null : $row;
    }

    /**
     * Researchers with active access on the given department, joined with
     * their lab. A researcher has no department_id; the link lives in
     * researcher_authorizations. Access is active when authorized_at is the
     * most recent decision (never revoked, or re-authorized after a revoke).
     *
     * @return list<array{researcher_id:int, email:string, first_name:string, last_name:string, lab_code:string, lab_name:string}>
     */
    public function listAuthorizedResearchers(int $departmentId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT ra.researcher_id, u.email, u.first_name, u.last_name,
                    l.code AS lab_code, l.name AS lab_name
             FROM researcher_authorizations ra
             JOIN researchers r ON r.id = ra.researcher_id
             JOIN users u ON u.id = r.id
             JOIN laboratories l ON l.id = r.laboratory_id
             WHERE ra.department_id = :dept
               AND ra.authorized_at IS NOT NULL
               AND (ra.rejected_at IS NULL OR ra.authorized_at > ra.rejected_at)
             ORDER BY u.last_name, u.first_name'
        );
        $stmt->execute(['dept' => $departmentId]);

        /** @var list<array{researcher_id:int, email:string, first_name:string, last_name:string, lab_code:string, lab_name:string}> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Whether the user is a student or teacher attached to the department.
     * Guards the activate/deactivate routes against forged ids (IDOR).
     */
    public function isDepartmentMember(int $userId, int $departmentId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM users u
             LEFT JOIN teachers t ON t.id = u.id
             LEFT JOIN students s ON s.id = u.id
             WHERE u.id = :id AND u.department_id = :dept
               AND (t.id IS NOT NULL OR s.id IS NOT NULL)'
        );
        $stmt->execute(['id' => $userId, 'dept' => $departmentId]);

        return $stmt->fetch() !== false;
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

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, email, password_hash, is_active FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }
    public function setVerifyToken(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verify_token = :token WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $userId]);
    }

    public function verifyEmail(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL
         WHERE email_verify_token = :token AND email_verified_at IS NULL'
        );
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    public function isEmailVerified(int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT email_verified_at FROM users WHERE id = :id'
        );
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        return $row !== false && $row['email_verified_at'] !== null;
    }

    public function isTeacherSpecialized(int $id): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1 FROM teachers WHERE id = :id AND is_specialised = true'
        );
        $query->execute(['id' => $id]);
        
        $row = $query->fetch();
        
        return (bool) $row;
    }

    public function getDepartmentIdByUserId(int $userId): mixed {
        $query = $this->pdo->prepare(
            'SELECT department_id FROM users WHERE id = :id'
        );
        $query->execute(['id' => $userId]);
        
        $row = $query->fetch();
        
        return $row;
    }
}