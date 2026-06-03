<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\UserRepository;
use Services\AuthService;

/**
 * Profile page (account info). Routed from `/profile`, renders inside the
 * authenticated chat shell. The view derives display name / initials / roles
 * from the current user, so the controller only has to pass `user`.
 */
class ProfileController extends Controller
{
    protected  AuthService $auth;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->auth = new AuthService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $this->render('pages/profile/index', [
            'user'      => $this->currentUser(),
            'page'      => 'profile',
            'pageTitle' => 'Mon profil',
            'title'     => 'Mon profil',
        ], 'chat');
    }
    public function deactivate(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $user = $this->currentUser() ?? $this->redirect('/login');

        $result = $this->auth->deactivateAccount($user['id']);

        if (!$result['success']) {
            $this->flash('error', $result['error']);
            $this->redirect('/profile');
        }

        // Destroy session — the user is now logged out.
        $this->auth->logout();

        // Start a fresh session just for the flash message.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $this->flash('success', 'Votre compte a été désactivé. Pour demander la suppression définitive de vos données, veuillez envoyer un email à dpo@univ-amu.fr.');
        $this->redirect('/login');
    }

    /**
     * Persists the chosen interface theme (auto / light / dark) and keeps
     * the session in sync so the layout reflects it immediately.
     */
    public function updateTheme(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $user = $this->currentUser();

        // UI choice → stored enum value (null = AUTO, follow the OS).
        $theme = match ((string) $this->input('theme', 'auto')) {
            'light' => 'LIGHT',
            'dark'  => 'DARK',
            default => null,
        };

        (new UserRepository(Database::getConnection()))->updateTheme((int) $user['id'], $theme);
        $_SESSION['user_theme'] = $theme;

        $this->flash('success', 'Thème mis à jour.');
        $this->redirect('/profile');
    }
}
