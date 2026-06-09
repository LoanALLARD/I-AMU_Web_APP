<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Services\EmailDomainService;
use Services\PlaceService;

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
        $service = new PlaceService(Database::getConnection());

        $this->renderPanel(
            'pages/superadmin/places',
            'Sites et departements',
            'places',
            [
                'places'      => $service->listPlaces(),
                'departments' => $service->listDepartments(),
            ]
        );
    }

    /** Adds a site (POST). */
    public function addPlace(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new PlaceService(Database::getConnection());
        $result  = $service->addPlace(
            (string) $this->input('name', ''),
            (string) $this->input('address', ''),
            (string) $this->input('city', ''),
            (string) $this->input('zip_code', '')
        );

        $this->flashResult($result, 'Site ajouté avec succès.');
        $this->redirect('/super-admin/places');
    }

    /** Deletes a site (POST). */
    public function deletePlace(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new PlaceService(Database::getConnection());
        $result  = $service->deletePlace((int) $this->input('id', 0));

        $this->flashResult($result, 'Site supprimé.');
        $this->redirect('/super-admin/places');
    }

    /** Adds a department to a site (POST). */
    public function addDepartment(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new PlaceService(Database::getConnection());
        $result  = $service->addDepartment(
            (int) $this->input('place_id', 0),
            (string) $this->input('name', ''),
            (string) $this->input('description', '')
        );

        $this->flashResult($result, 'Département ajouté avec succès.');
        $this->redirect('/super-admin/places');
    }

    /** Enables or disables a department (POST). */
    public function toggleDepartment(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service  = new PlaceService(Database::getConnection());
        $isActive = (string) $this->input('is_active', '') === '1';
        $result   = $service->setDepartmentActive((int) $this->input('id', 0), $isActive);

        $this->flashResult($result, $isActive ? 'Département réactivé.' : 'Département désactivé.');
        $this->redirect('/super-admin/places');
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
     * Flashes a success message or the service error, from a service result.
     *
     * @param array{success: true}|array{success: false, error: string} $result
     */
    private function flashResult(array $result, string $successMessage): void
    {
        if ($result['success']) {
            $this->flash('success', $successMessage);
        } else {
            $this->flash('error', $result['error']);
        }
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
