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
        $previewCode = $this->sessionModel->previewAccessCode();

        // Healthcheck Ollama pour la checklist "Avant de créer"
        $ollama = new \App\Services\OllamaService();
        $ollamaAvailable = $ollama->isAvailable();
        $ollamaModelsCount = $ollamaAvailable ? count($ollama->listModels()) : 0;

        $this->render('pages/session/create', [
            'title'              => 'Créer une session',
            'models'             => $models,
            'previewCode'        => $previewCode,
            'ollamaAvailable'    => $ollamaAvailable,
            'ollamaModelsCount'  => $ollamaModelsCount,
            'user'               => $this->currentUser(),
        ]);
    }

    /**
     * Traite la création d'une session.
     */
    public function store(): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $startsAt   = $this->input('starts_at');
        $duration   = (int) $this->input('duration_min', 90);
        // Calcule ends_at depuis durée si fourni, sinon utilise le champ direct.
        $endsAt     = $this->input('ends_at');
        if ($startsAt && !$endsAt && $duration > 0) {
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + $duration * 60);
        }

        $sessionId = $this->sessionModel->createSession([
            'name'                   => trim($this->input('name', '')),
            'access_code'            => trim($this->input('access_code', '')),
            'starts_at'              => $startsAt ?: null,
            'ends_at'                => $endsAt ?: null,
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
     * Formulaire de modification d'une session.
     * Bloqué si la session a déjà commencé, est terminée ou annulée.
     */
    public function edit(string $id): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $session = $this->sessionModel->find((int) $id);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        if (!$this->sessionModel->canBeModified($session)) {
            $this->flash('error', "Cette session ne peut plus être modifiée.");
            $this->redirect('/sessions');
            return;
        }

        $models = $this->llmModel->findActive();
        $authorized = array_column(
            $this->sessionModel->getAuthorizedModels((int) $id),
            'model_id'
        );

        $this->render('pages/session/edit', [
            'title'              => 'Modifier la session',
            'session'            => $session,
            'models'             => $models,
            'authorizedModelIds' => $authorized,
            'user'               => $this->currentUser(),
        ]);
    }

    /**
     * Traite la modification d'une session. Mêmes gardes que edit().
     */
    public function update(string $id): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $sessionId = (int) $id;
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        if (!$this->sessionModel->canBeModified($session)) {
            $this->flash('error', "Cette session ne peut plus être modifiée.");
            $this->redirect('/sessions');
            return;
        }

        $startsAt = $this->input('starts_at');
        $duration = (int) $this->input('duration_min', 90);
        $endsAt   = $this->input('ends_at');
        if ($startsAt && !$endsAt && $duration > 0) {
            $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + $duration * 60);
        }

        $this->sessionModel->updateSession($sessionId, [
            'name'                   => trim($this->input('name', '')),
            'starts_at'              => $startsAt ?: null,
            'ends_at'                => $endsAt ?: null,
            'system_prompt_override' => trim($this->input('system_prompt', '')),
            'max_input_size'         => (int) $this->input('max_input_size', 2000),
            'instructions'           => trim($this->input('instructions', '')),
            'type'                   => $this->input('type', 'TP'),
        ]);

        $modelIds = $this->input('models') ?? [];
        $this->sessionModel->setAuthorizedModels($sessionId, $modelIds);

        $this->flash('success', "Session mise à jour.");
        $this->redirect('/sessions');
    }

    /**
     * Annule une session (statut → CANCELLED).
     */
    public function cancel(string $id): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $sessionId = (int) $id;
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        $status = $session['status'] ?? null;
        if (in_array($status, [\App\Models\Session::STATUS_ENDED, \App\Models\Session::STATUS_CANCELLED], true)) {
            $this->flash('error', "Cette session ne peut plus être annulée.");
            $this->redirect('/sessions');
            return;
        }

        $this->sessionModel->cancel($sessionId);
        $this->flash('success', "Session annulée.");
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
