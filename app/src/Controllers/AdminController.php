<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Department-administrator console.
 *
 * Every action is gated by requireRole('department_admin'), which renders
 * the 403 page for any other visitor. The landing page is intentionally
 * minimal for now — management features (teacher habilitation, researcher
 * authorizations, model access) belong to spec 05.
 */
class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('department_admin');

        $this->render('pages/admin/dashboard', [
            'titrePage' => 'Administration',
            'user'      => $this->currentUser(),
        ]);
    }
}
