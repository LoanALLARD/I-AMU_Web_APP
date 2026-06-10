<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Services\ResourceService;
use Services\SessionService;

/**
 * Teacher-facing HTTP entry point for the Resource (course) feature.
 */
class ResourceController extends Controller
{
    private ResourceService $resources;
    private \Services\SessionService $sessions;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->resources = new ResourceService($pdo);
        $this->sessions   = new \Services\SessionService($pdo);
    }

    /**
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'chat'): void
    {
        $data['page']      ??= 'ressources';
        $data['user']      ??= $this->currentUser();
        $data['pageTitle'] ??= ($data['title'] ?? '');
        parent::render($template, $data, $layout);
    }

    /** GET /ressources */
    public function index(): void
    {
        $this->requireRole('teacher');
        $user = $this->currentUser();

        $this->render('pages/resources/index', [
            'title'      => 'Mes ressources',
            'navSection' => 'ressources',
            'ressources' => $this->resources->listForTeacher((int) ($user['id'] ?? 0)),
            'archived'   => $this->resources->listArchivedForTeacher((int) ($user['id'] ?? 0)),
            'sessions'   => $this->sessions->listForTeacher((int) ($user['id'] ?? 0)),
            'user'       => $user,
        ]);
    }

    /** GET /ressources/create */
    public function create(): void
    {
        $this->requireRole('teacher');
        $user = $this->currentUser();

        $this->render('pages/resources/create', [
            'title'            => 'Nouvelle ressource',
            'mode'             => 'create',
            'resource'         => null,
            'deptTeachers'     => $this->resources->listDepartmentTeachers(
                                      (int) ($user['department_id'] ?? 0),
                                      (int) ($user['id'] ?? 0)
                                  ),
            'assignedIds'      => [],
            'oldInput'         => $this->popOldInput(),
        ]);
    }

    /** POST /ressources/store */
    public function store(): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        $assignedIds = $this->extractAssignedTeacherIds();

        try {
            $row = $this->resources->create(
                $_POST,
                (int) ($user['id'] ?? 0),
                (int) ($user['department_id'] ?? 0),
                $assignedIds
            );
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->keepOldInput($_POST);
            $this->redirect('/ressources/create');
        }

        $this->flash('success', sprintf(
            'Ressource « %s » créée avec succès.',
            htmlspecialchars((string) $row['name'])
        ));
        $this->redirect('/ressources');
    }

    /** GET /ressources/{id}/edit */
    public function edit(string $id): void
    {
        $this->requireRole('teacher');
        $user     = $this->currentUser();
        $resource = $this->loadOwned((int) $id, (int) ($user['id'] ?? 0));

        $this->render('pages/resources/create', [
            'title'        => 'Modifier la ressource',
            'mode'         => 'edit',
            'resource'     => $resource,
            'deptTeachers' => $this->resources->listDepartmentTeachers(
                                  (int) ($user['department_id'] ?? 0),
                                  (int) ($user['id'] ?? 0)
                              ),
            'assignedIds'  => $this->resources->assignedTeacherIds((int) $id),
            'oldInput'     => $this->popOldInput(),
        ]);
    }

    /** POST /ressources/{id}/update */
    public function update(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        $assignedIds = $this->extractAssignedTeacherIds();

        try {
            $this->resources->update(
                (int) $id,
                $_POST,
                (int) ($user['id'] ?? 0),
                $assignedIds
            );
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
            $this->keepOldInput($_POST);
            $this->redirect('/ressources/' . (int) $id . '/edit');
        }

        $this->flash('success', 'Ressource mise à jour.');
        $this->redirect('/ressources');
    }

    /** POST /ressources/{id}/archive */
    public function archive(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        try {
            $this->resources->archive((int) $id, (int) ($user['id'] ?? 0));
            $this->flash('success', 'Ressource archivée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/ressources');
    }

    /** POST /ressources/{id}/restore */
    public function restore(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        try {
            $this->resources->restore((int) $id, (int) ($user['id'] ?? 0));
            $this->flash('success', 'Ressource restaurée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect('/ressources');
    }

    /** POST /ressources/{id}/publish */
    public function publish(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();
 
        try {
            $this->ressources->publish((int) $id, (int) ($user['id'] ?? 0));
            $this->flash('success', 'Ressource publiée.');
        } catch (\RuntimeException $e) {
            $this->flash('error', $e->getMessage());
        }
 
        $this->redirect('/ressources');
    }


    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Extracts and sanitises the `assigned_teachers[]` POST array.
     *
     * @return list<int>
     */
    private function extractAssignedTeacherIds(): array
    {
        $raw = $_POST['teachers'] ?? [];

        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_map('intval', $raw));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadOwned(int $resourceId, int $teacherId): array
    {
        try {
            return $this->resources->loadOwned($resourceId, $teacherId);
        } catch (\RuntimeException) {
            http_response_code(403);
            $this->render('pages/error', [
                'title'   => 'Accès refusé',
                'code'    => 403,
                'message' => 'Cette ressource ne vous appartient pas.',
            ]);
            exit;
        }
    }

    /**
     * @param array<string, mixed> $post
     */
    private function keepOldInput(array $post): void
    {
        unset($post['_csrf_token']);
        $_SESSION['_old_input'] = $post;
    }

    /**
     * @return array<string, mixed>
     */
    private function popOldInput(): array
    {
        $old = $_SESSION['_old_input'] ?? [];
        unset($_SESSION['_old_input']);

        return is_array($old) ? $old : [];
    }
}