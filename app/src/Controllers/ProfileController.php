<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\UserRepository;
use Services\AuthService;
use Services\TeacherSpecialisationService;
use Services\UserStatsService;

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

        $user = $this->currentUser();
        $roles = $user['roles'] ?? [];
        $isTeacher = in_array('teacher', $roles, true);
        // Only teachers and students use the LLM, so only they get usage stats.
        $usesLlm = $isTeacher || in_array('student', $roles, true);

        $this->render('pages/profile/index', [
            'user'              => $user,
            'page'              => 'profile',
            'pageTitle'         => 'Mon profil',
            'title'             => 'Mon profil',
            'specRequestStatus' => $isTeacher
                ? (new TeacherSpecialisationService(Database::getConnection()))
                    ->requestStatus((int) $user['id'])
                : 'none',
            'stats'             => $usesLlm
                ? (new UserStatsService(Database::getConnection()))
                    ->personal((int) $user['id'])
                : null,
        ], 'chat');
    }

    /**
     * POST /profile/request-specialisation — a teacher asks to be habilitated.
     */
    public function requestSpecialisation(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        if (!in_array('teacher', $user['roles'] ?? [], true)) {
            $this->renderForbidden();
        }

        $result = (new TeacherSpecialisationService(Database::getConnection()))
            ->requestSpecialisation((int) $user['id'], (string) $this->input('request', ''));

        $this->flash(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande d\'habilitation envoyée.' : $result['error']
        );
        $this->redirect('/profile');
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
     * POST /profile/update — edit the display name (first + last).
     */
    public function updateProfile(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $result = $this->auth->updateProfile(
            (int) $user['id'],
            (string) $this->input('first_name', ''),
            (string) $this->input('last_name', '')
        );

        $this->flash(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Profil mis à jour.' : $result['error']
        );
        $this->redirect('/profile');
    }

    /**
     * POST /profile/password — change the password after verifying the old one.
     */
    public function changePassword(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $result = $this->auth->changePassword(
            (int) $user['id'],
            (string) $this->input('current_password', ''),
            (string) $this->input('new_password', ''),
            (string) $this->input('new_password_confirm', '')
        );

        $this->flash(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Mot de passe modifié.' : $result['error']
        );
        $this->redirect('/profile');
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

        // UI choice → stored enum value. AUTO = follow the OS preference.
        $theme = match ((string) $this->input('theme', 'auto')) {
            'light' => 'LIGHT',
            'dark'  => 'DARK',
            default => 'AUTO',
        };

        (new UserRepository(Database::getConnection()))->updateTheme((int) $user['id'], $theme);
        $_SESSION['user_theme'] = $theme;

        $this->flash('success', 'Thème mis à jour.');
        $this->redirect('/profile');
    }

    public function updateResearchOpposition(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $user = $this->currentUser();

        $opposed = true;

        (new UserRepository(Database::getConnection()))->updateResearchOpposition((int) $user['id'], $opposed);

        $this->flash('success', 'Préférence de recherche mise à jour.');
        $this->redirect('/profile');
    }
}
