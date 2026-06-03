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
}
