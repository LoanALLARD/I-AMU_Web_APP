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
        $places = new \Models\PlaceRepository(Database::getConnection());

        $this->renderPanel(
            'pages/superadmin/department-admins',
            'Administrateurs de département',
            'department-admins',
            ['departments' => $this->allDepartments()]
        );
    }

    public function places(): void
    {
        $service = new PlaceService(Database::getConnection());

        $this->renderPanel(
            'pages/superadmin/places',
            'Sites et départements',
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

    /** Updates a site's branding: display name, logo, primary color (POST). */
    public function updateBranding(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $service = new PlaceService(Database::getConnection());
        $result  = $service->updateBranding(
            (int) $this->input('id', 0),
            (string) $this->input('display_name', ''),
            (string) $this->input('primary_color', ''),
            $_FILES['logo'] ?? null,
            $_FILES['favicon'] ?? null,
            (bool) $this->input('remove_logo', false),
            (bool) $this->input('remove_favicon', false)
        );

        $this->flashResult($result, 'Personnalisation enregistrée.');
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
    /** Sends a signed invitation link by email (POST). */
    public function inviteDepartmentAdmin(): void
    {
        $this->requireSuperAdmin();
        $this->verifyCsrf();

        $email        = strtolower(trim((string) $this->input('email', '')));
        $departmentId = (int) $this->input('department_id', 0);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $departmentId === 0) {
            $this->flash('error', 'Email ou département invalide.');
            $this->redirect('/super-admin/department-admins');
        }

        $service = new \Services\AdminInviteService(Database::getConnection());
        $token   = $service->makeToken($email, $departmentId);

        // Derive the base URL from the current request so the link points to
        // the host the super admin actually reached the app through.
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost:8085';
        $link   = $scheme . '://' . $host
            . '/admin-invite/accept?token=' . urlencode($token);;

        $mail = new \Services\MailService();
        $sent = $mail->send(
            $email,
            'Invitation administrateur de département — I-AMU',
            '<h2>Invitation administrateur de département</h2>'
            . '<p>Vous avez été invité à administrer un département sur I-AMU.</p>'
            . '<p>Cliquez sur le lien ci-dessous pour créer votre compte (valable 7 jours) :</p>'
            . '<p><a href="' . htmlspecialchars($link) . '">Activer mon compte administrateur</a></p>'
            . '<p>Si vous n\'attendiez pas cette invitation, ignorez cet email.</p>'
        );

        $this->flash(
            $sent ? 'success' : 'error',
            $sent ? 'Invitation envoyée à ' . htmlspecialchars($email) . '.'
                  : "L'envoi de l'email a échoué."
        );
        $this->redirect('/super-admin/department-admins');
    }

    /**
     * Flat list of departments with their place, for the invite select.
     *
     * @return list<array{id:int, label:string}>
     */
    private function allDepartments(): array
    {
        $pdo  = Database::getConnection();
        $stmt = $pdo->query(
            'SELECT d.id, d.name, p.name AS place_name
             FROM departments d
             JOIN places p ON p.id = d.place_id
             WHERE d.is_active = TRUE
             ORDER BY p.name, d.name'
        );
        $rows = $stmt->fetchAll();

        return array_map(
            static fn ($r) => [
                'id'    => (int) $r['id'],
                'label' => $r['name'] . ' (' . $r['place_name'] . ')',
            ],
            $rows
        );
    }
}
