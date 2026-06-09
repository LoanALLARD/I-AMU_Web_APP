<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Services\EmailDomainService;

/**
 * Super administrator panel (see docs/specs/05-admin-research.md A.0.1 / A.2).
 * One sub-page per power; department-admins and places are still placeholders.
 * Every page is guarded by requireSuperAdmin().
 */
class SuperAdminController extends Controller
{
    /** No landing page of its own; redirect to the first section. */
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
        $service = new EmailDomainService(Database::getConnection());

        $this->renderPanel(
            'pages/superadmin/email-domains',
            'Domaines email',
            'email-domains',
            [
                'domains' => $service->list(),
                'roles'   => $service->roles(),
            ]
        );
    }

    /** Adds an authorized email domain (POST). */
    public function addEmailDomain(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new EmailDomainService(Database::getConnection());
        $result  = $service->add(
            (string) $this->input('domain', ''),
            (string) $this->input('role', ''),
            (int) ($this->currentSuperAdmin()['id'] ?? 0)
        );

        if ($result['success']) {
            $this->flash('success', 'Domaine ajouté avec succès.');
        } else {
            $this->flash('error', $result['error']);
        }

        $this->redirect('/super-admin/email-domains');
    }

    /** Changes a domain's role (POST). */
    public function changeEmailDomainRole(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new EmailDomainService(Database::getConnection());
        $result  = $service->changeRole(
            (int) $this->input('id', 0),
            (string) $this->input('role', '')
        );

        if ($result['success']) {
            $this->flash('success', 'Rôle mis à jour.');
        } else {
            $this->flash('error', $result['error']);
        }

        $this->redirect('/super-admin/email-domains');
    }

    /** Enables or disables a domain (POST). */
    public function toggleEmailDomain(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service  = new EmailDomainService(Database::getConnection());
        $id       = (int) $this->input('id', 0);
        $isActive = (string) $this->input('is_active', '') === '1';

        $result = $service->setActive($id, $isActive);

        if ($result['success']) {
            $this->flash('success', $isActive ? 'Domaine réactivé.' : 'Domaine désactivé.');
        } else {
            $this->flash('error', $result['error']);
        }

        $this->redirect('/super-admin/email-domains');
    }

    /**
     * Shared render path: guard, super admin identity, full-page body class and
     * the active nav key. `$extra` carries page-specific view data.
     *
     * @param array<string, mixed> $extra
     */
    private function renderPanel(
        string $template,
        string $titrePage,
        string $activeNav,
        array $extra = []
    ): void {
        $this->requireSuperAdmin();

        $this->render(
            $template,
            [
                'titrePage'  => $titrePage,
                'superAdmin' => $this->currentSuperAdmin(),
                'bodyClass'  => 'superadmin-body--full',
                'activeNav'  => $activeNav,
            ] + $extra,
            'superadmin'
        );
    }
}
