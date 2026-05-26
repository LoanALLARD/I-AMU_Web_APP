<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Session;
use App\Models\LlmModel;
use App\Models\Conversation;

class SessionController extends Controller
{
    private Session $sessionModel;
    private LlmModel $llmModel;

    public function __construct()
    {
        $this->sessionModel = new Session();
        $this->llmModel     = new LlmModel();
    }

    /**
     * Liste les sessions de l'enseignant.
     */
    public function index(): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        // Récupère toutes les sessions (à filtrer par enseignant en production)
        $sessions = $this->sessionModel->findAll('starts_at DESC');
        $this->render('pages/session/index', [
            'title'    => 'Mes sessions',
            'sessions' => $sessions,
            'user'     => $this->currentUser(),
        ]);
    }

    /**
     * Formulaire de création de session.
     */
    public function create(): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $models = $this->llmModel->findActive();
        $this->render('pages/session/create', [
            'title'  => 'Créer une session',
            'models' => $models,
            'user'   => $this->currentUser(),
        ]);
    }

    /**
     * Traite la création d'une session.
     */
    public function store(): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $sessionId = $this->sessionModel->createSession([
            'name'                   => trim($this->input('name', '')),
            'starts_at'              => $this->input('starts_at'),
            'ends_at'                => $this->input('ends_at'),
            'system_prompt_override' => trim($this->input('system_prompt', '')),
            'max_input_size'         => (int) $this->input('max_input_size', 2000),
            'instructions'           => trim($this->input('instructions', '')),
            'type'                   => $this->input('type', 'TP'),
        ]);

        // Attribution des modèles autorisés
        $modelIds = $this->input('models') ?? [];
        foreach ($modelIds as $modelId) {
            $this->sessionModel->authorizeModel($sessionId, (int) $modelId);
        }

        $session = $this->sessionModel->find($sessionId);
        $this->flash('success', "Session créée ! Code d'accès : {$session['access_code']}");
        $this->redirect('/sessions');
    }

    /**
     * Tableau de bord d'une session (supervision enseignant).
     */
    public function dashboard(string $id): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $session = $this->sessionModel->find((int) $id);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        $conversationModel = new Conversation();
        $conversations = $conversationModel->findBySession((int) $id);
        $models = $this->sessionModel->getAuthorizedModels((int) $id);

        $this->render('pages/session/dashboard', [
            'title'         => 'Dashboard - ' . $session['name'],
            'session'       => $session,
            'conversations' => $conversations,
            'models'        => $models,
            'user'          => $this->currentUser(),
        ]);
    }

    /**
     * Rejoindre une session via code d'accès (étudiant).
     */
    public function join(): void
    {
        $this->requireAuth();

        $code = strtoupper(trim($this->input('access_code', '')));
        $session = $this->sessionModel->findByAccessCode($code);

        if (!$session) {
            $this->flash('error', "Code d'accès invalide.");
            $this->redirect('/chat');
            return;
        }

        if (!$this->sessionModel->isActive($session['session_id'])) {
            $this->flash('error', "Cette session n'est pas active actuellement.");
            $this->redirect('/chat');
            return;
        }

        // Crée une conversation liée à la session
        $conversationModel = new Conversation();
        $type = $session['type'] === 'EXAM' ? 'EXAM' : 'COURSE';
        $convId = $conversationModel->createConversation(
            $_SESSION['user_id'],
            $session['name'],
            $type,
            $session['session_id']
        );

        // Redirige vers le mode examen si c'est un examen
        if ($session['type'] === 'EXAM') {
            $this->redirect("/exam/$convId");
        } else {
            $this->redirect("/chat/$convId");
        }
    }
}
