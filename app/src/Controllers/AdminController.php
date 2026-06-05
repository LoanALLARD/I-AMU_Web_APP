<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Services\ResearcherAuthorizationService;

/**
 * Department-administrator console.
 *
 * Every action is gated by requireRole('department_admin'), which renders
 * the 403 page for any other visitor. Actions are scoped to the admin's own
 * department via currentDepartmentId().
 */
class AdminController extends Controller
{
    public function index(): void
    {
        $this->requireRole('department_admin');

        $pdo = Database::getConnection();
        $departmentId = $this->currentDepartmentId();

        $this->render('pages/admin/dashboard', [
            'titrePage'          => 'Administration',
            'user'               => $this->currentUser(),
            'department'         => (new PlaceRepository($pdo))->departmentWithPlace($departmentId),
            'pendingResearchers' => (new ResearcherAuthorizationService($pdo))->listPending($departmentId),
        ]);
    }

    public function approveResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->approve($researcherId, $this->currentDepartmentId(), (int) $this->currentUser()['id']);

        $this->flash($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande chercheur validee.' : $result['error']);
        $this->redirect('/admin');
    }

    public function rejectResearcher(): void
    {
        $this->requireRole('department_admin');
        $this->verifyCsrf();

        $researcherId = (int) $this->input('researcher_id');
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->reject($researcherId, $this->currentDepartmentId(), (int) $this->currentUser()['id']);

        $this->flash($result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande chercheur refusee.' : $result['error']);
        $this->redirect('/admin');
    }

    /** The department this admin is scoped to; 403 if none (role implies one). */
    protected function currentDepartmentId(): int
    {
        $departmentId = $this->currentUser()['department_id'] ?? null;
        if ($departmentId === null) {
            $this->renderForbidden();
        }
        return $departmentId;
    }
}
