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

    /** The department this admin is scoped to; 403 if none (role implies one). */
    protected function currentDepartmentId(): int
    {
        $departmentId = $this->currentUser()['department_id'] ?? null;
        if ($departmentId === null) {
            $this->renderForbidden();
        }
        return $departmentId;
    }

    /** Aborts with 403 unless the target belongs to the admin's department. */
    protected function assertSameDepartment(int $targetDepartmentId): void
    {
        if ($targetDepartmentId !== $this->currentDepartmentId()) {
            $this->renderForbidden();
        }
    }
}
