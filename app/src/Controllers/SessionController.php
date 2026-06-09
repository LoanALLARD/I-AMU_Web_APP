<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Domain\SessionException;
use Services\CreateSessionForm;
use Services\DocumentService;
use Services\SessionService;
use Models\AiRepository;
use Throwable;

/**
 * Teacher- and student-facing HTTP entry point for the Session feature.
 *
 * MVC style: the controller reads HTTP input, delegates every rule to
 * SessionService, and renders a view with plain arrays. It owns only the
 * cross-cutting concerns: auth/CSRF guards, ownership checks, mapping domain
 * exceptions to flash + redirect, and old-input retention on validation error.
 */
class SessionController extends Controller
{
    private SessionService $sessions;
    private DocumentService $documents;

    public function __construct()
    {
        $pdo = Database::getConnection();
        $this->sessions  = new SessionService($pdo);
        $this->documents = new DocumentService($pdo);
    }

    /**
     * Session pages render inside layout/chat.php (the authenticated shell).
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

    /** GET /sessions — teacher's list (owned + supervised read-only). */
    public function index(): void
    {
        $this->requireRole('teacher');
        $user = $this->currentUser();

        $this->render('pages/session/index', [
            'title'      => 'Mes sessions',
            'navSection' => 'sessions',
            'sessions'   => $this->sessions->listForTeacher((int) ($user['id'] ?? 0)),
            'supervised' => $this->sessions->listSupervisedForTeacher((int) ($user['id'] ?? 0)),
            'user'       => $user,
        ]);
    }

    /** GET /sessions/create — create form. */
    public function create(): void
    {
        $this->requireRole('teacher');
        $user = $this->currentUser();
        $data = $this->sessions->createFormData((int) ($user['id'] ?? 0));

        $this->render('pages/session/create', [
            'title'                => 'Nouvelle session',
            'navSection'           => 'sessions',
            'mode'                 => 'create',
            'session'              => null,
            'models'               => $data['models'],
            'resources'            => $data['resources'],
            'authorizedModelIds'   => [],
            'previewCode'          => $data['previewCode'],
            'previewCodeFormatted' => $data['previewCodeFormatted'],
            'user'                 => $user,
            'oldInput'             => $this->popOldInput(),
        ]);
    }

    /** POST /sessions/store — create handler. */
    public function store(): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $user = $this->currentUser();

        $form = CreateSessionForm::fromPost($_POST);
        if ($form['errors'] !== []) {
            foreach ($form['errors'] as $error) {
                $this->flash('error', $error);
            }
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        if (!$this->sessions->resourceBelongsTo((int) $form['data']['resourceId'], (int) ($user['id'] ?? 0))) {
            $this->flash('error', 'Ressource introuvable ou inaccessible.');
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        try {
            $session = $this->sessions->create($form['data'], (int) ($user['id'] ?? 0));
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/create');
        }

        $code = $session->accessCodeFormatted();
        $this->flash('success', $code !== null
            ? sprintf("Session « %s » créée. Code d'accès : %s", $session->name(), $code)
            : sprintf("Session « %s » créée (brouillon). Le code d'accès sera généré au démarrage.", $session->name()));
        $this->redirect('/sessions/' . $session->id());
    }

    /** GET /sessions/{id}/edit — edit form. */
    public function edit(string $id): void
    {
        $this->requireRole('teacher');
        $session = $this->loadOwned((int) $id);

        $user = $this->currentUser();
        $data = $this->sessions->editFormData($session, (int) ($user['id'] ?? 0));

        $this->render('pages/session/create', [
            'title'                => 'Modifier la session',
            'navSection'           => 'sessions',
            'mode'                 => 'edit',
            'session'              => $session,
            'models'               => $data['models'],
            'resources'            => $data['resources'],
            'authorizedModelIds'   => $data['authorizedModelIds'],
            'previewCode'          => $data['previewCode'],
            'previewCodeFormatted' => $data['previewCodeFormatted'],
            'user'                 => $user,
            'oldInput'             => $this->popOldInput(),
        ]);
    }

    /** POST /sessions/{id}/update — edit handler. */
    public function update(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->loadOwned($sessionId);

        $form = CreateSessionForm::fromPostForUpdate($_POST);
        if ($form['errors'] !== []) {
            foreach ($form['errors'] as $error) {
                $this->flash('error', $error);
            }
            $this->keepOldInput($_POST);
            $this->redirect('/sessions/' . $sessionId . '/edit');
        }

        try {
            $this->sessions->update($sessionId, $form['data']);
        } catch (SessionException $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/sessions');
        }

        $this->flash('success', 'Session mise à jour.');
        $this->redirect('/sessions/' . $sessionId);
    }

    /** POST /sessions/{id}/start */
    public function start(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->loadOwned($sessionId);

        try {
            $this->sessions->start($sessionId);
            $this->flash('success', 'Session démarrée.');
        } catch (SessionException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /** POST /sessions/{id}/end */
    public function end(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->loadOwned($sessionId);

        try {
            $this->sessions->end($sessionId);
            $this->flash('success', 'Session terminée.');
        } catch (SessionException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /** POST /sessions/{id}/cancel */
    public function cancel(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $sessionId = (int) $id;
        $this->loadOwned($sessionId);

        try {
            $this->sessions->cancel($sessionId);
            $this->flash('success', 'Session annulée.');
        } catch (SessionException $e) {
            $this->flash('error', $e->getMessage());
        }
        $this->redirect('/sessions/' . $sessionId);
    }

    /** GET /sessions/{id} — dashboard (owner: full; responsible: read-only). */
    public function dashboard(string $id): void
    {
        $this->requireRole('teacher');
        $session = $this->loadViewable((int) $id);

        $view = $this->sessions->dashboard($session);

        $this->render('pages/session/dashboard', [
            'title'      => $view['name'],
            'navSection' => 'sessions',
            'view'       => $view,
            'canManage'  => $this->canManage($session),
            'students'   => $this->sessions->enrolledStudents((int) $id),
            'documents'  => $this->documents->listForSession((int) $id),
            'user'       => $this->currentUser(),
        ]);
    }

    /** GET /sessions/{id}/monitor — read-only supervision of enrolled students. */
    public function monitor(string $id): void
    {
        $this->requireRole('teacher');
        $session = $this->loadViewable((int) $id);

        $conversationId = (int) $this->query('conversation', 0);
        $view           = $this->sessions->monitor($session, $conversationId);

        if ($view === null) {
            $this->flash('error', "Le suivi n'est disponible que pour une session en cours ou terminée.");
            $this->redirect('/sessions/' . (int) $id);
        }

        $this->render('pages/session/monitor', [
            'title'      => 'Suivi · ' . $session->name(),
            'navSection' => 'sessions',
            'view'       => $view,
            'canManage'  => $this->canManage($session),
            'user'       => $this->currentUser(),
        ]);
    }

    /**
     * POST /sessions/{id}/student-status — the owner (de)activates a student's
     * enrollment from the monitor view. Deactivating removes the student from
     * the session chat (read-only on next load) and bars re-joining.
     */
    public function setStudentActive(string $id): void
    {
        $this->requireRole('teacher');
        $this->verifyCsrf();
        $session = $this->loadOwned((int) $id);

        $studentId = (int) $this->input('student_id', 0);
        $active    = $this->input('active') === '1';

        if ($studentId > 0) {
            $this->sessions->setStudentActive((int) $session->id(), $studentId, $active);
            $this->flash(
                'success',
                $active
                    ? 'Étudiant réactivé dans la session.'
                    : 'Étudiant désactivé : il est déconnecté de la session et ne peut plus la rejoindre.'
            );
        }

        $this->redirect('/sessions/' . (int) $id . '/monitor');
    }

    /**
     * GET /sessions/{id}/export — JSON research export of the whole session
     * (students -> conversations -> interactions). No platform-side
     * anonymisation (cf. spec 06): the export carries identity.
     *
     * Accessible to the owning teacher, or to a researcher / department admin.
     */
    public function export(string $id): void
    {
        $this->requireAuth();
        $user  = $this->currentUser();
        $roles = $user['roles'] ?? [];

        $session = $this->sessions->find((int) $id);
        if ($session === null) {
            $this->flash('error', 'Session introuvable.');
            $this->redirect('/sessions');
        }

        // Owner or read-only responsible (teacher_resources), or researcher /
        // department admin. canView() covers owner + responsible.
        $canView     = $this->sessions->canView($session, (int) ($user['id'] ?? 0));
        $canResearch = in_array('researcher', $roles, true) || in_array('department_admin', $roles, true);
        if (!$canView && !$canResearch) {
            $this->forbidden();
        }

        // Filters from the export modal. When `configured` is absent (e.g. a
        // direct link), default to the full export.
        $configured = $this->query('configured') !== null;
        $excludeRaw = $this->query('exclude_ids', []);
        $excludeIds = is_array($excludeRaw) ? array_map('intval', $excludeRaw) : [];

        $options = [
            'excludeIds'       => $excludeIds,
            'includePrompts'   => !$configured || $this->query('include_prompts')   !== null,
            'includeResponses' => !$configured || $this->query('include_responses') !== null,
        ];

        $data     = $this->sessions->exportSessionData($session, $options);
        $codeSlug = preg_replace('/[^A-Za-z0-9]/', '', (string) ($session->accessCode() ?? ('s' . $session->id())));
        $filename = 'session-' . $codeSlug . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** GET /sessions/join — student form. */
    public function showJoin(): void
    {
        $this->requireRole('student');
        $this->render('pages/session/join', [
            'title' => 'Rejoindre une session',
        ]);
    }

    /** POST /sessions/join — student joins and lands in the conversation. */
    public function join(): void
    {
        $this->requireRole('student');
        $this->verifyCsrf();

        $rawCode = (string) $this->input('access_code', '');
        $user    = $this->currentUser();

        try {
            $result = $this->sessions->join($rawCode, (int) ($user['id'] ?? 0));
        } catch (Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/sessions/join');
        }

        $this->flash('success', $result['alreadyJoined']
            ? "Vous êtes déjà inscrit à « {$result['sessionName']} » — voici votre conversation."
            : "Vous avez rejoint la session « {$result['sessionName']} ».");
        $this->redirect('/chat/' . $result['conversationId']);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Loads a session, redirecting on miss and 403-ing if it is not owned by
     * the current teacher. Returns the (owned) session on success.
     */
    private function loadOwned(int $sessionId): \Domain\Session
    {
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            $this->flash('error', 'Session introuvable.');
            $this->redirect('/sessions');
        }

        $user = $this->currentUser();
        if ($user === null || $session->teacherId() === null || (int) $user['id'] !== $session->teacherId()) {
            $this->forbidden();
        }

        return $session;
    }

    /**
     * Loads a session for READ-ONLY access: the owner, or a teacher attached to
     * the resource via teacher_resources (responsible). 403s otherwise. Used by
     * the consultation routes (monitor/dashboard); mutating actions keep
     * loadOwned. Pair with canManage() to gate owner-only controls in the view.
     */
    private function loadViewable(int $sessionId): \Domain\Session
    {
        $session = $this->sessions->find($sessionId);
        if ($session === null) {
            $this->flash('error', 'Session introuvable.');
            $this->redirect('/sessions');
        }

        $user = $this->currentUser();
        if ($user === null || !$this->sessions->canView($session, (int) $user['id'])) {
            $this->forbidden();
        }

        return $session;
    }

    /** Whether the current user owns the session (vs. a read-only responsible). */
    private function canManage(\Domain\Session $session): bool
    {
        $user = $this->currentUser();

        return $user !== null && $session->teacherId() === (int) $user['id'];
    }

    private function forbidden(): never
    {
        http_response_code(403);
        $this->render('pages/error', [
            'title'   => 'Accès refusé',
            'code'    => 403,
            'message' => 'Cette session ne vous appartient pas.',
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
    
    public function getModelsByResource(): void
    {
        $resourceId = (int) $this->query('resource_id', 0);
        $depId = $this->currentUser()['department_id'];

        $pdo = Database::getConnection();
        $aiRepo = new AiRepository($pdo);
        
        $modelsByResource = $aiRepo->findAllActiveByResource($resourceId);
        $modelsByDeptAndShared = $aiRepo->findAllActiveBySession(null, $depId);

        $allModels = array_merge($modelsByResource, $modelsByDeptAndShared);

        $allModels = array_intersect_key(
            $allModels, 
            array_unique(array_column($allModels, 'id'))
        );
        $allModels = array_values($allModels);

        header('Content-Type: application/json');
        echo json_encode(['models' => $allModels]);
        exit;
    }
}
