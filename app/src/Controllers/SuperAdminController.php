<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Super administrator panel.
 *
 * Guarded by requireSuperAdmin(): only a super admin session reaches it; a
 * normal `users` session has no `super_admin_id` key (sessions are mutually
 * exclusive) and is redirected to the super admin login.
 *
 * This iteration is a SHELL (just a title). The platform-management features
 * (invite department admins, manage places/departments/email-domains) belong
 * to spec 05 and arrive later.
 */
class SuperAdminController extends Controller
{
    public function index(): void
    {
        $this->requireSuperAdmin();

        $this->render(
            'pages/superadmin/dashboard',
            [
                'titrePage'  => 'Administration',
                'superAdmin' => $this->currentSuperAdmin(),
            ],
            'superadmin'
        );
    }
}
