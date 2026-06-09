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
 * This iteration is the NAVIGATION SHELL: one sub-page per super admin power
 * (see docs/specs/05-admin-research.md A.0.1 / A.2). The pages are
 * placeholders — the business logic (mail invites, place/department creation,
 * email-domain CRUD, traceability) arrives in later iterations.
 */
class SuperAdminController extends Controller
{
    /**
     * `/super-admin` has no landing page of its own — send the super admin
     * straight to the first section.
     */
    public function index(): void
    {
        $this->requireSuperAdmin();
        $this->redirect('/super-admin/department-admins');
    }

    public function departmentAdmins(): void
    {
        $this->renderPanel(
            'pages/superadmin/department-admins',
            'Administrateurs de departement',
            'department-admins'
        );
    }

    public function places(): void
    {
        $this->renderPanel('pages/superadmin/places', 'Sites et departements', 'places');
    }

    public function emailDomains(): void
    {
        $this->renderPanel('pages/superadmin/email-domains', 'Domaines email', 'email-domains');
    }

    /**
     * Shared render path for every panel page: enforces the guard, injects the
     * super admin identity, the full-page body class and the active nav key so
     * the shared navigation can highlight the current section.
     */
    private function renderPanel(string $template, string $titrePage, string $activeNav): void
    {
        $this->requireSuperAdmin();

        $this->render(
            $template,
            [
                'titrePage'  => $titrePage,
                'superAdmin' => $this->currentSuperAdmin(),
                'bodyClass'  => 'superadmin-body--full',
                'activeNav'  => $activeNav,
            ],
            'superadmin'
        );
    }
}
