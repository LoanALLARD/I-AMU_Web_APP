<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Profile page (account info). Routed from `/profile`, renders inside the
 * authenticated chat shell. The view derives display name / initials / roles
 * from the current user, so the controller only has to pass `user`.
 */
class ProfileController extends Controller
{
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

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

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

}
