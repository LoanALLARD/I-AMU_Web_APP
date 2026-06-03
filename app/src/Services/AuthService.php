<?php

declare(strict_types=1);

namespace Services;

use App\Infrastructure\Persistence\PdoConnection;

/**
 * Minimal authentication service against the real `users` table.
 *
 * Temporary bridge between the legacy login flow (kept on
 * Controllers\LoginController) and the real database introduced by spec 00.
 * To be rewritten on top of UserRepositoryInterface / App\Application
 * once spec 01 (Auth & Account) is properly implemented.
 *
 * Schema alignment notes:
 *   - Tables and columns are plural / `id`-based per init-scripts/IAMU_db.sql:
 *     `users.id`, `users.last_login_at`, `teachers.id`, `students.id`.
 *   - `users` no longer carries `gdpr_consent` / `gdpr_consent_at`; the
 *     equivalent is now `consent_at` + `consent_version`. We do not touch
 *     these fields here — they are owned by spec 06 (RGPD).
 */
final class AuthService
{
    public function __construct(
        private readonly PdoConnection $db,
    ) {
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Email et mot de passe requis.'];
        }

        $row = $this->db->query(
            'SELECT id, email, password_hash, first_name, last_name, is_active
             FROM users WHERE email = :email',
            ['email' => $email]
        )->fetch();

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants invalides.'];
        }

        if (!$row['is_active']) {
            return ['success' => false, 'error' => 'Ce compte est désactivé.'];
        }

        $userId = (int) $row['id'];

        $this->db->query(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id',
            ['id' => $userId]
        );

        $_SESSION['user_id']         = $userId;
        $_SESSION['user_email']      = (string) $row['email'];
        $_SESSION['user_first_name'] = (string) $row['first_name'];
        $_SESSION['user_last_name']  = (string) $row['last_name'];
        $_SESSION['roles']           = $this->resolveRoles($userId);
        session_regenerate_id(true);

        return ['success' => true];
    }

    /**
     * Registers a new user.
     *
     * Behaviour:
     *   - Format-validates the form input (email, password >= 8 chars,
     *     password_confirm match, RGPD consent ticked).
     *   - Looks up the email domain to derive the role:
     *       @etu.univ-amu.fr  -> student auto
     *       @univ-amu.fr      -> teacher auto
     *       anything else     -> rejected (per CLAUDE.md §7).
     *     Once spec 05 ships an admin UI for `email_domain_configs`, this
     *     lookup will move to the DB; the contract here doesn't change.
     *   - Hashes the password with bcrypt.
     *   - Wraps `INSERT users` + `INSERT teachers|students` in a single
     *     transaction so a half-created account can never linger.
     *
     * Does NOT auto-login: the caller flashes a success message and
     * redirects to /login (LoginController::register already does that).
     *
     * @param array<string, mixed> $data
     * @return array{success: true} | array{success: false, error: string}
     */
    public function register(array $data): array
    {
        $email           = trim((string) ($data['email']            ?? ''));
        $password        = (string) ($data['password']             ?? '');
        $passwordConfirm = (string) ($data['password_confirm']     ?? '');
        $firstName       = trim((string) ($data['first_name']      ?? ''));
        $lastName        = trim((string) ($data['last_name']       ?? ''));
        $rgpdConsent     = (bool)  ($data['rgpd_consent']          ?? false);

        // ----- Format validation ------------------------------------
        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return ['success' => false, 'error' => 'Tous les champs sont obligatoires.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Email invalide.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit faire au moins 8 caractères.'];
        }
        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }
        if (!$rgpdConsent) {
            return ['success' => false, 'error' => 'Vous devez accepter les conditions RGPD pour créer un compte.'];
        }

        // ----- Role lookup ------------------------------------------
        $role = $this->resolveRoleFromDomain($email);
        if ($role === null) {
            return [
                'success' => false,
                'error'   => "Seuls les emails AMU sont acceptés (@etu.univ-amu.fr ou @univ-amu.fr).",
            ];
        }

        // ----- Email uniqueness -------------------------------------
        $existing = $this->db
            ->query('SELECT 1 FROM users WHERE email = :email', ['email' => $email])
            ->fetch();
        if ($existing !== false) {
            return ['success' => false, 'error' => 'Cet email est déjà utilisé.'];
        }

        // ----- Insert -----------------------------------------------
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->query(
                'INSERT INTO users (email, password_hash, first_name, last_name, consent_at, consent_version)
                 VALUES (:email, :hash, :fn, :ln, NOW(), :ver)
                 RETURNING id',
                [
                    'email' => $email,
                    'hash'  => $hash,
                    'fn'    => $firstName,
                    'ln'    => $lastName,
                    'ver'   => '1.0',
                ]
            );
            $userId = (int) $stmt->fetchColumn();

            // The role table only carries the FK to users.id; default
            // values are good for is_specialised (false) / title (null) /
            // student_number (null) — the user can fill them later.
            if ($role === 'teacher') {
                $this->db->query('INSERT INTO teachers (id) VALUES (:id)', ['id' => $userId]);
            } else {
                $this->db->query('INSERT INTO students (id) VALUES (:id)', ['id' => $userId]);
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            return [
                'success' => false,
                'error'   => "Erreur lors de l'enregistrement. Merci de réessayer.",
            ];
        }

        return ['success' => true];
    }

    public function deactivateAccount(int $userId): array
    {
        try {
            $stmt = $this->db->query(
                'UPDATE users SET is_active = FALSE WHERE id = :id AND is_active = TRUE',
                ['id' => $userId]
            );

            if ($stmt->rowCount() === 0) {
                return [
                    'success' => false,
                    'error'   => 'Le compte est déjà désactivé ou introuvable.',
                ];
            }

            return ['success' => true];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => 'Erreur lors de la désactivation du compte.',
            ];
        }
    }


    /**
     * Hardcoded domain → role mapping (CLAUDE.md §7).
     *
     * To be replaced by a lookup in `email_domain_configs` once spec 05
     * is implemented. Returning null means "no auto-role for this domain".
     */
    private function resolveRoleFromDomain(string $email): ?string
    {
        $lower = strtolower($email);
        if (str_ends_with($lower, '@etu.univ-amu.fr')) {
            return 'student';
        }
        if (str_ends_with($lower, '@univ-amu.fr')) {
            return 'teacher';
        }
        return null;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Resolves the user's roles by checking the specialisation tables.
     * A single user may carry multiple roles.
     *
     * @return list<string>
     */
    private function resolveRoles(int $userId): array
    {
        $roles = [];

        if ($this->existsIn('teachers', $userId)) {
            $roles[] = 'teacher';
        }
        if ($this->existsIn('students', $userId)) {
            $roles[] = 'student';
        }
        // researchers / department_administrators exist on the live
        // schema but their HTTP surface belongs to spec 05.

        return $roles;
    }

    private function existsIn(string $table, int $userId): bool
    {
        // Table whitelist enforced by the call sites (teachers/students/...).
        $row = $this->db->query(
            "SELECT 1 FROM {$table} WHERE id = :id",
            ['id' => $userId]
        )->fetch();
        return $row !== false;
    }
}
