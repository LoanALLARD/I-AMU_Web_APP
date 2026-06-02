<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Ports\ModelReadRepositoryInterface;
use App\Application\Ports\ResourceReadRepositoryInterface;
use App\Application\Services\CancelSessionService;
use App\Application\Services\CreateSessionService;
use App\Application\Services\EndSessionService;
use App\Application\Services\GetSessionDashboardService;
use App\Application\Services\JoinSessionService;
use App\Application\Services\ListMySessionsService;
use App\Application\Services\StartSessionService;
use App\Application\Services\UpdateSessionService;
use App\Domain\Exceptions\SessionAlreadyEndedException;
use App\Domain\Exceptions\SessionAlreadyStartedException;
use App\Domain\Exceptions\SessionCancelledException;
use App\Domain\Exceptions\SessionNotEditableException;
use App\Domain\Exceptions\SessionNotFoundException;
use App\Domain\Repositories\SessionRepositoryInterface;
use App\Http\Forms\CreateSessionForm;
use Core\Controller;

/**
 * Teacher-facing HTTP entry point for the Session aggregate.
 *
 * Every mutation goes through a corresponding Application service, every
 * read through a view-model. The controller's only jobs are: auth/CSRF
 * guarding, request validation (delegated to CreateSessionForm), mapping
 * domain exceptions to flash + redirect, and rendering.
 *
 * Resource ownership: `sessions` no longer carries `teacher_id` directly —
 * the owning teacher is `resources.owner_id`. The controller enforces this
 * invariant when CREATING a session (the requested resource must belong to
 * the current teacher) and trusts the repository to surface the derived
 * teacher_id when LOADING a session for edit/start/end/cancel/dashboard.
 */
final class SessionController extends Controller
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessions,
        private readonly ModelReadRepositoryInterface $models,
        private readonly ResourceReadRepositoryInterface $resources,
        private readonly CreateSessionService $createSession,
        private readonly UpdateSessionService $updateSession,
        private readonly StartSessionService $startSession,
        private readonly EndSessionService $endSession,
        private readonly CancelSessionService $cancelSession,
        private readonly JoinSessionService $joinSession,
        private readonly ListMySessionsService $listMySessions,
        private readonly GetSessionDashboardService $getDashboard,
    ) {
    }

    /**
     * Every session page renders inside Layout/chat.php (the universal
     * authenticated shell: sidebar + topbar). The override injects the
     * variables that shell expects (page flag + current user + page
     * title for the topbar breadcrumb) without forcing every action to
     * repeat them.
     *
     * @param array<string, mixed> $data
     */
    protected function render(string $template, array $data = [], string $layout = 'chat'): void
    {
        $data['page']      ??= 'sessions';
        $data['user']      ??= $this->currentUser();
        $data['pageTitle'] ??= ($data['title'] ?? '');
        parent::render($template, $data, $layout);
    }

    /**
     * GET /sessions — teacher's list.
     */
    public function index(): void
    {
        $this->requireRole('teacher');
        $user  = $this->currentUser();
        $views = $this->listMySessions->execute($user['id']);

        $this->render('pages/session/index', [
            'title'       => 'Mes sessions',
            'breadcrumb'  => 'sessions',
            'navSection'  => 'sessions',
            'sessions'    => $views,
            'user'        => $user,
        ]);
    }

    /**
     * GET /sessions/create — create form.
     */
    public function create(): void
    {
        $this->requireRole('teacher');

        $user            = $this->currentUser();
        $models          = $this->models->findAllActive();
        $resources       = $this->resources->findAllByOwner($user['id']);
        $previewCode     = $this->sessions->generateUniqueAccessCode();

        $this->render('pages/session/create', [
            'title'              => 'Nouvelle session',
            'breadcrumb'         => 'sessions / nouvelle',
            'navSection'         => 'sessions',
            'mode'               => 'create',
            'session'            => null,
            'models'             => $models,
            'resources'          => $resources,
            'authorizedModelIds' => [],
            'previewCode'        => $previewCode,
            'user'               => $user,
            'oldInput'           => $this->popOldInput(),
        ]);
    }

    /**
     * POST /sessions/store — create handler.
     */
    public function store(): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();

        $user   = $this->currentUser();
        $result = CreateSessionForm::fromPost($_POST);
        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $e) {
                $this->flash('error', $e);
            }
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        // Ownership: the chosen resource MUST belong to the current teacher.
        $resource = $this->resources->findById($result['request']->resourceId);
        if ($resource === null || $resource->ownerId !== $user['id']) {
            $this->flash('error', "Ressource introuvable ou inaccessible.");
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        try {
            $session = $this->createSession->execute($result['request'], $user['id']);
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        $this->flash('success', sprintf(
            "Session « %s » créée. Code d'accès : %s",
            $session->name(),
            $session->accessCode()->formatted()
        ));
        $this->redirect('/sessions/' . $session->id());
    }

    /**
     * GET /sessions/{id}/edit — edit form.
     */
    public function edit(string $id): void
    {
        $this->requireRole('teacher');
        $sessionId = (int) $id;

        $session = $this->sessions->findById($sessionId);
        if ($session === null) {
            $this->flash('error', "Session introuvable.");
            $this->redirect('/sessions');
        }
        if (!$this->ownsSession($session->teacherId())) {
            $this->forbidden();
        }

        $user               = $this->currentUser();
        $models             = $this->models->findAllActive();
        $resources          = $this->resources->findAllByOwner($user['id']);
        $authorizedModelIds = $this->sessions->authorizedModelIdsOf($sessionId);

        $this->render('pages/session/create', [
            'title'              => 'Modifier la session',
            'breadcrumb'         => 'sessions / modifier',
            'navSection'         => 'sessions',
            'mode'               => 'edit',
            'session'            => $session,
            'models'             => $models,
            'resources'          => $resources,
            'authorizedModelIds' => $authorizedModelIds,
            'previewCode'        => $session->accessCode(),
            'user'               => $user,
            'oldInput'           => $this->popOldInput(),
        ]);
    }

    /**
     * POST /sessions/{id}/update — edit handler.
     */
    public function update(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;

        $existing = $this->sessions->findById($sessionId);
        if ($existing === null) {
            $this->flash('error', "Session introuvable.");
            $this->redirect('/sessions');
        }
        if (!$this->ownsSession($existing->teacherId())) {
            $this->forbidden();
        }

        $result = CreateSessionForm::fromPostForUpdate($_POST);
        if ($result['errors'] !== []) {
            foreach ($result['errors'] as $e) {
                $this->flash('error', $e);
            }
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/' . $sessionId . '/edit');
        }

        try {
            $this->updateSession->execute($sessionId, $result['request']);
        } catch (SessionNotEditableException | SessionNotFoundException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/sessions');
        }

        $this->flash('success', 'Session mise à jour.');
        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * POST /sessions/{id}/start
     */
    public function start(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->guardOwnership($sessionId);

        try {
            $this->startSession->execute($sessionId);
            $this->flash('success', 'Session démarrée.');
        } catch (SessionAlreadyStartedException | SessionAlreadyEndedException | SessionCancelledException | SessionNotFoundException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * POST /sessions/{id}/end
     */
    public function end(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->guardOwnership($sessionId);

        try {
            $this->endSession->execute($sessionId);
            $this->flash('success', 'Session terminée.');
        } catch (SessionAlreadyEndedException | SessionCancelledException | SessionNotFoundException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * POST /sessions/{id}/cancel
     */
    public function cancel(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->guardOwnership($sessionId);

        try {
            $this->cancelSession->execute($sessionId);
            $this->flash('success', 'Session annulée.');
        } catch (SessionAlreadyEndedException | SessionCancelledException | SessionNotFoundException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /**
     * GET /sessions/{id} — dashboard.
     */
    public function dashboard(string $id): void
    {
        $this->requireRole('teacher');
        $sessionId = (int) $id;

        $entity = $this->sessions->findById($sessionId);
        if ($entity === null) {
            $this->flash('error', "Session introuvable.");
            $this->redirect('/sessions');
        }
        if (!$this->ownsSession($entity->teacherId())) {
            $this->forbidden();
        }

        $view = $this->getDashboard->execute($sessionId);

        $this->render('pages/session/dashboard', [
            'title'        => $view->name,
            'breadcrumb'   => 'sessions / ' . $view->accessCode,
            'navSection'   => 'sessions',
            'view'         => $view,
            'user'         => $this->currentUser(),
        ]);
    }

    /**
     * POST /sessions/join — student endpoint.
     */
    public function join(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $rawCode = (string) $this->input('access_code', '');
        $userId  = $this->currentUser()['id'];

        try {
            $session = $this->joinSession->execute($rawCode, $userId);
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/');
        }

        // Spec 03 will redirect to /chat/{conversationId}; for now, send
        // the student back to the home page with a confirmation flash.
        $this->flash('success', "Vous avez rejoint la session « {$session->name()} ».");
        $this->redirect('/');
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function ownsSession(?int $teacherId): bool
    {
        $user = $this->currentUser();
        return $user !== null && $teacherId !== null && $user['id'] === $teacherId;
    }

    private function guardOwnership(int $sessionId): void
    {
        $existing = $this->sessions->findById($sessionId);
        if ($existing === null) {
            $this->flash('error', "Session introuvable.");
            $this->redirect('/sessions');
        }
        if (!$this->ownsSession($existing->teacherId())) {
            $this->forbidden();
        }
    }

    private function forbidden(): never
    {
        http_response_code(403);
        $this->render('pages/error', [
            'title'   => 'Accès refusé',
            'code'    => 403,
            'message' => "Cette session ne vous appartient pas.",
        ]);
        exit;
    }

    /**
     * @param array<string, mixed> $post
     */
    private function keepOldInput(array $post): void
    {
        unset($post['_csrf_token'], $post['access_code']);
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
