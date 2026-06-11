<?php

declare(strict_types=1);

namespace Models;

use PDO;
use Throwable;
use Models\SuperAdministratorRepository;

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
     *     promo_year: int,
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
            } else if ($role == 'teacher') {
                $roleStmt = $this->pdo->prepare("INSERT INTO teachers (id) VALUES (:id)");
                $roleStmt->execute(['id' => $userId]);
            }else{
                $roleStmt = $this->pdo->prepare("INSERT INTO students (id,year) VALUES (:id,:year)");
                $roleStmt->execute(['id' => $userId, 'year' => $user['promo_year']]);
            }

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    /**
     * List members of a department by cursor.
     * * @param int $departmentId
     * @param array{last_name: string, first_name: string, id: int}|null $cursor
     * @param int $limit
     * @return list<array<string, mixed>>
     */
    public function listDepartmentMembers(int $departmentId, ?array $cursor = null, int $limit = 30): array
    {
        $params = ['dept' => $departmentId, 'limit' => $limit];
        
        $whereCursor = "";
        if ($cursor !== null) {
            $whereCursor = " AND (
                u.last_name > :c_last_name 
                OR (u.last_name = :c_last_name AND u.first_name > :c_first_name)
                OR (u.last_name = :c_last_name AND u.first_name = :c_first_name AND u.id > :c_id)
            )";
            $params['c_last_name'] = $cursor['last_name'];
            $params['c_first_name'] = $cursor['first_name'];
            $params['c_id'] = $cursor['id'];
        }

        $sql = "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, u.last_login_at,
                    u.created_at, u.email_verified_at, u.consent_at, u.consent_version, u.research_opposed,
                    t.is_specialised, t.title, s.student_number, s.year,
                    CASE WHEN t.id IS NOT NULL THEN 'teacher' ELSE 'student' END AS role
                FROM users u
                LEFT JOIN teachers t ON t.id = u.id
                LEFT JOIN students s ON s.id = u.id
                WHERE u.department_id = :dept
                AND (t.id IS NOT NULL OR s.id IS NOT NULL)
                {$whereCursor}
                ORDER BY u.last_name ASC, u.first_name ASC, u.id ASC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, is_int($val) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        
        $stmt->execute();

        /** @var list<array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();
        return $rows;
    }

    /** Number of students and teachers attached to a department, for pagination. */
    public function CountDepartmentMembers(int $departmentId): int
    {
        $query = $this->pdo->prepare(
            'SELECT count(*) From users u
            LEFT JOIN teachers t ON t.id = u.id
            LEFT JOIN students s ON s.id = u.id
            WHERE u.department_id = :dep_id
            AND (t.id IS NOT NULL OR s.id IS NOT NULL)'
        );

        $query->execute(['dep_id' => $departmentId]);

        return (int) $query->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findUsers(string $firstSecondName, int $depId): array
    {
        $query = $this->pdo->prepare(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, u.last_login_at,
                    u.created_at, u.email_verified_at, u.consent_at, u.consent_version, u.research_opposed,
                    t.is_specialised, t.title, s.student_number, s.year,
                    CASE WHEN t.id IS NOT NULL THEN 'teacher' ELSE 'student' END AS role
             FROM users u
             LEFT JOIN teachers t ON t.id = u.id
             LEFT JOIN students s ON s.id = u.id
             WHERE u.department_id = :dep_id
               AND (t.id IS NOT NULL OR s.id IS NOT NULL)
               AND (u.first_name LIKE :name OR u.last_name LIKE :name)
             ORDER BY u.last_name ASC, u.first_name ASC"
        );

        $query->execute([
            'name'   => '%' . $firstSecondName . '%',
            'dep_id' => $depId
        ]);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->fetchAll();
        return $rows;
    }
    /**
     * A single member row (same projection as listDepartmentMembers), to
     * re-render its table row after a state change. Scoped to the department so
     * it cannot read a member of another one.
     *
     * @return array<string, mixed>|null
     */
    public function findMemberRow(int $userId, int $departmentId): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id, u.email, u.first_name, u.last_name, u.is_active, u.last_login_at,
                    u.created_at, u.email_verified_at, u.consent_at, u.consent_version, u.research_opposed,
                    t.is_specialised, t.title, s.student_number, s.year,
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

    /**
     * Deactivates an active user. Returns the number of rows changed (0 when the
     * user was already inactive), so the caller can tell whether anything happened.
     */
    public function deactivate(int $userId): int
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET is_active = FALSE WHERE id = :id AND is_active = TRUE'
        );
        $stmt->execute(['id' => $userId]);
        return $stmt->rowCount();
    }

    /**
     * Reactivates an inactive user. Returns the number of rows changed (0 when the
     * user was already active).
     */
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
    /** Stores the email-verification token sent to the user. */
    public function setVerifyToken(int $userId, string $token): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verify_token = :token WHERE id = :id'
        );
        $stmt->execute(['token' => $token, 'id' => $userId]);
    }

    /**
     * Marks the email tied to the token as verified and clears the token.
     * Returns true only when a still-unverified account matched (so a reused
     * or stale token returns false).
     */
    public function verifyEmail(string $token): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL
         WHERE email_verify_token = :token AND email_verified_at IS NULL'
        );
        $stmt->execute(['token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /** Whether the teacher has been granted the specialised flag. */
    public function isTeacherSpecialized(int $id): bool
    {
        $query = $this->pdo->prepare(
            'SELECT 1 FROM teachers WHERE id = :id AND is_specialised = true'
        );
        $query->execute(['id' => $id]);
        
        $row = $query->fetch();
        
        return (bool) $row;
    }

    /**
     * Returns the user's department row (`['department_id' => ...]`), or false
     * when the user does not exist. Note: despite the name this yields the
     * fetched row, not a bare id — read `['department_id']` from it.
     */
    public function getDepartmentIdByUserId(int $userId): mixed {
        $query = $this->pdo->prepare(
            'SELECT department_id FROM users WHERE id = :id'
        );
        $query->execute(['id' => $userId]);
        
        $row = $query->fetch();
        
        return $row;
    }

    /**
     * Creates a department-admin: users row + department_administrators row.
     * invited_by_id references the super admin who issued the invitation and the email is marked verified
     *
     * @param array{email: string, password_hash: string, first_name: string, last_name: string, department_id: int|null, consent_version?: string } $user
     *
     * @throws Throwable if any statement fails (the transaction is rolled back).
     */
    public function createDepartmentAdmin(array $user, int $invitedById): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (email, password_hash, first_name, last_name, department_id, consent_at, consent_version, email_verified_at)
                 VALUES (:email, :hash, :fn, :ln, :department_id, NOW(), :ver, NOW())
                 RETURNING id'
            );
            $stmt->execute([
                'email'         => $user['email'],
                'hash'          => $user['password_hash'],
                'fn'            => $user['first_name'],
                'ln'            => $user['last_name'],
                'department_id' => $user['department_id'],
                'ver'           => $user['consent_version'] ?? '1.0',
            ]);
            $userId = (int) $stmt->fetchColumn();

            $roleStmt = $this->pdo->prepare(
                'INSERT INTO department_administrators (id, invited_by_id)
                 VALUES (:id, :invited_by)'
            );
            $roleStmt->execute([
                'id'         => $userId,
                'invited_by' => $invitedById > 0 ? $invitedById : null,
            ]);

            $this->pdo->commit();

            return $userId;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Lists every department administrator with their department and site,
     * for the super admin management panel. Ordered active first, then by name.
     *
     * @return list<array{id:int, email:string, first_name:?string, last_name:?string, is_active:bool, department_name:?string, place_name:?string}>
     */
    public function listDepartmentAdmins(): array
    {
        $stmt = $this->pdo->query(
            'SELECT u.id, u.email, u.first_name, u.last_name, u.is_active,
                    d.name AS department_name, p.name AS place_name
             FROM department_administrators da
             JOIN users u ON u.id = da.id
             LEFT JOIN departments d ON d.id = u.department_id
             LEFT JOIN places p ON p.id = d.place_id
             ORDER BY u.is_active DESC, u.last_name, u.first_name'
        );

        return array_map(
            static fn (array $row): array => [
                'id'              => (int) $row['id'],
                'email'           => (string) $row['email'],
                'first_name'      => $row['first_name'] !== null ? (string) $row['first_name'] : null,
                'last_name'       => $row['last_name'] !== null ? (string) $row['last_name'] : null,
                'is_active'       => (bool) $row['is_active'],
                'department_name' => $row['department_name'] !== null ? (string) $row['department_name'] : null,
                'place_name'      => $row['place_name'] !== null ? (string) $row['place_name'] : null,
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * Records whether the user opposes the research use of their data (GDPR),
     * toggling the `research_opposed` flag.
     */
    public function updateResearchOpposition(int $userId, bool $opposed): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE users
            SET research_opposed = :opposed
            WHERE id = :id'
        );

        $stmt->bindValue('opposed', $opposed, PDO::PARAM_BOOL);
        $stmt->bindValue('id', $userId, PDO::PARAM_INT);

        $stmt->execute();
    }

    /**
     * Hard-deletes a department admin user. The department_administrators row
     * is removed automatically. Returns the number of deleted users rows
     */
    public function deleteAdmin(int $adminId): int
    {
        $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = :id');
        $stmt->execute(['id' => $adminId]);

        return $stmt->rowCount();
    }

    /** Hashes and stores a new password for the user. Returns the statement success. */
    public function updatePassword(int $id, string $password): bool
    {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        
        $query = $this->pdo->prepare(
            'UPDATE users SET password_hash = :password WHERE id = :id'
        );

        return $query->execute([
            'password' => $hash,
            'id'       => $id
        ]);
    }

    /** Updates the user's first name. Returns the statement success. */
    public function updateFirstName(int $id, string $firstName): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE users SET first_name = :first_name WHERE id = :id'
        );

        return $query->execute([
            'first_name' => $firstName,
            'id'         => $id
        ]);
    }

    /** Updates the user's last name. Returns the statement success. */
    public function updateLastName(int $id, string $lastName): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE users SET last_name = :last_name WHERE id = :id'
        );

        return $query->execute([
            'last_name' => $lastName,
            'id'        => $id
        ]);
    }

    /** Updates the user's email. Returns the statement success. */
    public function updateEmail(int $id, string $email): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE users SET email = :email WHERE id = :id'
        );

        return $query->execute([
            'email' => $email,
            'id'    => $id
        ]);
    }
    /** Updates the teacher's academic title (on the `teachers` row). Returns the statement success. */
    public function updateTitle(int $id, string $title): bool
    {
        $query = $this->pdo->prepare(
            'UPDATE teachers SET title = :title WHERE id = :id'
        );

        return $query->execute([
            'title' => $title,
            'id'    => $id
        ]);
    }
}