<?php

declare(strict_types=1);

namespace Services;

use Data\Database;
use Models\UserRepository;

/**
 * Authentication service against the real `users` table.
 *
 * Owns the application logic (input validation, domain -> role mapping,
 * password hashing/verification, session population). All SQL lives in
 * UserRepository, which this service drives.
 *
 * Known remaining shortcut: the domain -> role mapping is hardcoded in
 * resolveRoleFromDomain(); it will move to a lookup in `email_domain_configs`
 * once spec 05 ships the admin UI. The contract here will not change.
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
    private UserRepository $users;

    public function __construct()
    {
        // Data\Database singleton connection. Matches how the controllers
        $this->users = new UserRepository(Database::getConnection());
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Email et mot de passe requis.'];
        }

        $row = $this->users->findByEmail($email);

        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants invalides.'];
        }

        if (!$row['is_active']) {
            return ['success' => false, 'error' => 'Ce compte est désactivé.'];
        }

        $userId = (int) $row['id'];

        $this->users->touchLastLogin($userId);

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
     *   - Delegates persistence to UserRepository::createUserWithRole(),
     *     which creates the user + role row in a single transaction so a
     *     half-created account can never linger.
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
        $validationError = $this->validateRegistration(
            $email,
            $password,
            $passwordConfirm,
            $firstName,
            $lastName,
            $rgpdConsent
        );
        if ($validationError !== null) {
            return ['success' => false, 'error' => $validationError];
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
        if ($this->users->emailExists($email)) {
            return ['success' => false, 'error' => 'Cet email est déjà utilisé.'];
        }

        // ----- Insert (user + role row, atomically) -----------------
        try {
            $this->users->createUserWithRole(
                [
                    'email'           => $email,
                    'password_hash'   => password_hash($password, PASSWORD_DEFAULT),
                    'first_name'      => $firstName,
                    'last_name'       => $lastName,
                    'consent_version' => '1.0',
                ],
                $role
            );
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error'   => "Erreur lors de l'enregistrement. Merci de réessayer.",
            ];
        }

        return ['success' => true];
    }

    /**
     * Validates the registration form input. Returns the (French) error
     * message to surface to the user, or null when every field is valid.
     */
    private function validateRegistration(
        string $email,
        string $password,
        string $passwordConfirm,
        string $firstName,
        string $lastName,
        bool $rgpdConsent
    ): ?string {
        if ($email === '' || $password === '' || $firstName === '' || $lastName === '') {
            return 'Tous les champs sont obligatoires.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Email invalide.';
        }
        if (strlen($password) < 8) {
            return 'Le mot de passe doit faire au moins 8 caractères.';
        }
        if ($password !== $passwordConfirm) {
            return 'Les mots de passe ne correspondent pas.';
        }
        if (!$rgpdConsent) {
            return 'Vous devez accepter les conditions RGPD pour créer un compte.';
        }

        return null;
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

        if ($this->users->hasRole($userId, 'teachers')) {
            $roles[] = 'teacher';
        }
        if ($this->users->hasRole($userId, 'students')) {
            $roles[] = 'student';
        }
        // researchers / department_administrators exist on the live
        // schema but their HTTP surface belongs to spec 05.

        return $roles;
    }
}
