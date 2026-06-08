<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Services\SuperAdminAuthService;

/**
 * Login / logout for super administrators.
 *
 * Reached ONLY by direct URL (/super-admin/login) — there is deliberately no
 * link to it anywhere in the user-facing app (see SPEC-superadmin-auth.md,
 * decision D1). The path being unlinked is not a security measure in itself;
 * the protection comes from the authentication below.
 */
class SuperAdminAuthController extends Controller
{
    private SuperAdminAuthService $auth;

    public function __construct()
    {
        $this->auth = new SuperAdminAuthService(Database::getConnection());
    }

    /**
     * Displays the dedicated super admin login form.
     */
    public function showLogin(): void
    {
        if ($this->isSuperAdmin()) {
            $this->redirect('/super-admin');
        }
        $this->render(
            'pages/superadmin/login',
            ['titrePage' => 'Connexion administration'],
            'superadmin'
        );
    }

    /**
     * Processes the super admin login form.
     */
    public function login(): void
    {
        $this->verifyCsrf();

        $email    = trim((string) $this->input('email', ''));
        $password = (string) $this->input('password', '');

        $result = $this->auth->login($email, $password);

        if (!$result['success']) {
            $this->render(
                'pages/superadmin/login',
                [
                    'titrePage' => 'Connexion administration',
                    'error'     => $result['error'],
                    'email'     => $email,
                ],
                'superadmin'
            );
            return;
        }

        $this->redirect('/super-admin');
    }

    /**
     * Destroys the super admin session and returns to the login form.
     */
    public function logout(): void
    {
        $this->verifyCsrf();
        $this->auth->logout();
        $this->redirect('/super-admin/login');
    }
}
