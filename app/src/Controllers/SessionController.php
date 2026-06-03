<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Domain\SessionException;
use Services\CreateSessionForm;
use Services\SessionService;
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

    public function __construct()
    {
        $this->sessions = new SessionService(Database::getConnection());
    }

    /**
     * Session pages render inside Layout/chat.php (the authenticated shell).
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

    /** GET /sessions — teacher's list. */
    public function index(): void
    {
        $this->requireRole('teacher');
        $user = $this->currentUser();

        $this->render('pages/session/index', [
            'title'      => 'Mes sessions',
            'navSection' => 'sessions',
            'sessions'   => $this->sessions->listForTeacher((int) ($user['id'] ?? 0)),
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

        $this->flash('success', sprintf(
            "Session « %s » créée. Code d'accès : %s",
            $session->name(),
            $session->accessCodeFormatted()
        ));
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

    /** GET /sessions/{id} — dashboard. */
    public function dashboard(string $id): void
    {
        $this->requireRole('teacher');
        $session = $this->loadOwned((int) $id);

        $view = $this->sessions->dashboard($session);

        $this->render('pages/session/dashboard', [
            'title'      => $view['name'],
            'navSection' => 'sessions',
            'view'       => $view,
            'user'       => $this->currentUser(),
        ]);
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
}
