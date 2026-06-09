<?php

declare(strict_types=1);

namespace Services;

use Core\Csrf;
use Models\SuperAdministratorRepository;
use PDO;

/**
 * Authentication service for super administrators.
 *
 * Super admins live in their own table (`super_administrators`), isolated
 * from `users` for defense in depth (see docs/specs/05-admin-research.md
 * A.0). The session identity is therefore kept under dedicated
 * `super_admin_*` keys, never `user_*`.
 *
 * Session isolation is also MUTUALLY EXCLUSIVE (see SPEC-superadmin-auth.md,
 * decision D3): logging in here wipes any in-progress user session, and a
 * normal user login wipes the super admin keys (handled in AuthService).
 * One browser session is never both at once.
 */
final class SuperAdminAuthService
{
    private SuperAdministratorRepository $superAdmins;

    public function __construct(PDO $pdo)
    {
        $this->superAdmins = new SuperAdministratorRepository($pdo);
    }

    /**
     * @return array{success: true} | array{success: false, error: string}
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Email et mot de passe requis.'];
        }

        $row = $this->superAdmins->findByEmail($email);

        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants invalides.'];
        }

        $id = (int) $row['id'];

        // Stamp the login BEFORE touching the session, so a DB error can never
        // leave a half-authenticated session (session keys set but the request
        // 500s afterwards).
        $this->superAdmins->touchLastLogin($id);

        // Exclusivity (D3): start from a clean session so no leftover user
        // identity survives, then re-key as super admin only.
        $_SESSION = [];
        session_regenerate_id(true);

        $_SESSION['super_admin_id']         = $id;
        $_SESSION['super_admin_email']      = (string) $row['email'];
        $_SESSION['super_admin_first_name'] = (string) $row['first_name'];
        $_SESSION['super_admin_last_name']  = (string) $row['last_name'];

        // Rotate the CSRF token on the login boundary (invalidate any
        // in-flight pre-login form).
        Csrf::rotate();

        return ['success' => true];
    }

    /**
     * Destroys the current session entirely. Same teardown as
     * AuthService::logout() — clears the array, expires the cookie,
     * destroys the session.
     */
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
}
