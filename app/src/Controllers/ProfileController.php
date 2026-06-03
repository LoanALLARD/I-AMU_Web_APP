<?php
declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Services\AuthService;

/**
 * Handles the profile page and RGPD account deactivation.
 *
 * Routes:
 *   GET  /profile              → show()
 *   POST /profile/deactivate   → deactivate()
 */
final class ProfileController extends Controller
{
    public function __construct(
        private readonly AuthService $auth,
    )
    {
    }

    /**
     * GET /profile — renders the user's profile page.
     */
    public function show(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $this->render('pages/profile/index', [
            'user' => $user,
            'title' => 'Mon profil',
        ]);
    }

    /**
     * POST /profile/deactivate — soft-deletes (deactivates) the account.
     *
     * Sets `users.is_active = FALSE`, then destroys the session so
     * the user is logged out immediately. On next login attempt the
     * AuthService will reject them ("Ce compte est désactivé.").
     *
     * RGPD note: actual data erasure requires an explicit request
     * to the data controller by email — this is indicated to the user
     * on the profile page.
     */
    public function deactivate(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $user = $this->currentUser();
        if ($user === null) {
            $this->redirect('/login');
        }

        $result = $this->auth->desactivateAccount($user['id']);

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

        // MAIL a changé !!!!
        $this->flash('success', 'Votre compte a été désactivé. Pour demander la suppression définitive de vos données, veuillez envoyer un email à dpo@univ-amu.fr.');
        $this->redirect('/login');
    }
}