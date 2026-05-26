<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Conversation;
use App\Models\Interaction;
use App\Models\LlmModel;
use App\Services\OllamaService;

class ChatController extends Controller
{
    private Conversation $conversationModel;
    private Interaction $interactionModel;
    private LlmModel $llmModel;
    private OllamaService $ollamaService;

    public function __construct()
    {
        $this->conversationModel = new Conversation();
        $this->interactionModel  = new Interaction();
        $this->llmModel          = new LlmModel();
        $this->ollamaService     = new OllamaService();
    }

    /**
     * Page principale du chat (mode libre).
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->requireGdprConsent();

        $user = $this->currentUser();
        $conversations = $this->conversationModel->findByUser($user['id']);
        $this->maybeSyncModels();
        $models = $this->llmModel->findActive();

        $this->render('pages/chat/index', [
            'title'         => 'Chat - Mode Libre',
            'conversations' => $conversations,
            'models'        => $models,
            'user'          => $user,
        ]);
    }

    /**
     * Crée une nouvelle conversation.
     */
    public function createConversation(): void
    {
        $this->requireAuth();

        $name      = trim($this->input('name', 'Nouvelle conversation'));
        $type      = $this->input('type', 'FREE');
        $sessionId = $this->input('session_id') ? (int) $this->input('session_id') : null;

        $conversationId = $this->conversationModel->createConversation(
            $_SESSION['user_id'],
            $name,
            $type,
            $sessionId
        );

        $this->json(['success' => true, 'conversation_id' => $conversationId]);
    }

    /**
     * Affiche une conversation existante.
     */
    public function show(string $id): void
    {
        $this->requireAuth();
        $this->requireGdprConsent();

        $conversation = $this->conversationModel->find((int) $id);
        if (!$conversation || $conversation['user_id'] !== $_SESSION['user_id']) {
            $this->redirect('/chat');
            return;
        }

        $interactions = $this->interactionModel->findByConversation((int) $id);
        $this->maybeSyncModels();
        $models = $this->llmModel->findActive();
        $conversations = $this->conversationModel->findByUser($_SESSION['user_id']);

        $this->render('pages/chat/index', [
            'title'               => $conversation['name'],
            'currentConversation' => $conversation,
            'interactions'        => $interactions,
            'conversations'       => $conversations,
            'models'              => $models,
            'user'                => $this->currentUser(),
        ]);
    }

    /**
     * Envoie un prompt au LLM et sauvegarde l'interaction (endpoint AJAX).
     */
    public function sendPrompt(): void
    {
        $this->requireAuth();

        $conversationId = (int) $this->input('conversation_id');
        $modelId        = (int) $this->input('model_id');
        $prompt         = trim($this->input('prompt', ''));

        if (empty($prompt)) {
            $this->json(['error' => 'Le prompt ne peut pas être vide.'], 400);
            return;
        }

        // Vérification que la conversation appartient à l'utilisateur
        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation || $conversation['user_id'] !== $_SESSION['user_id']) {
            $this->json(['error' => 'Conversation introuvable.'], 404);
            return;
        }

        // Récupération du modèle
        $model = $this->llmModel->find($modelId);
        if (!$model || !$model['is_active']) {
            $this->json(['error' => 'Modèle indisponible.'], 400);
            return;
        }

        // Construction de l'historique des messages pour le contexte
        $history = $this->interactionModel->findByConversation($conversationId);
        $messages = [];
        foreach ($history as $interaction) {
            $messages[] = ['role' => 'user', 'content' => $interaction['prompt']];
            if ($interaction['response']) {
                $messages[] = ['role' => 'assistant', 'content' => $interaction['response']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        // Récupération du system prompt si session
        $systemPrompt = null;
        if ($conversation['session_id']) {
            $sessionModel = new \App\Models\Session();
            $session = $sessionModel->find($conversation['session_id']);
            $systemPrompt = $session['system_prompt_override'] ?? null;
        }

        try {
            // Envoi au LLM via Ollama
            $result = $this->ollamaService->chat($model['name'], $messages, $systemPrompt);

            // Sauvegarde de l'interaction
            $interactionId = $this->interactionModel->saveInteraction([
                'prompt'          => $prompt,
                'response'        => $result['response'],
                'latency'         => $result['latency'],
                'input_tokens'    => $result['input_tokens'],
                'output_tokens'   => $result['output_tokens'],
                'conversation_id' => $conversationId,
                'model_id'        => $modelId,
            ]);

            $this->json([
                'success'        => true,
                'response'       => $result['response'],
                'interaction_id' => $interactionId,
                'latency'        => $result['latency'],
                'model'          => $model['name'],
            ]);
        } catch (\Exception $e) {
            $this->json([
                'error' => 'Erreur de communication avec le modèle : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Archive une conversation.
     */
    public function archive(string $id): void
    {
        $this->requireAuth();

        $conversation = $this->conversationModel->find((int) $id);
        if ($conversation && $conversation['user_id'] === $_SESSION['user_id']) {
            $this->conversationModel->archive((int) $id);
        }

        $this->json(['success' => true]);
    }

    /**
     * Envoie un prompt au LLM en STREAMING (Server-Sent Events).
     * Renvoie la réponse mot par mot au navigateur.
     */
    public function sendPromptStream(): void
    {
        $this->requireAuth();

        $conversationId = (int) $this->input('conversation_id');
        $modelId        = (int) $this->input('model_id');
        $prompt         = trim($this->input('prompt', ''));

        // En-têtes SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) { ob_end_flush(); }

        $sendEvent = function (string $event, array $data) {
            echo "event: $event\n";
            echo 'data: ' . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
            flush();
        };

        if (empty($prompt)) {
            $sendEvent('error', ['message' => 'Le prompt ne peut pas être vide.']);
            return;
        }

        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation || $conversation['user_id'] !== $_SESSION['user_id']) {
            $sendEvent('error', ['message' => 'Conversation introuvable.']);
            return;
        }

        $model = $this->llmModel->find($modelId);
        if (!$model || !$model['is_active']) {
            $sendEvent('error', ['message' => 'Modèle indisponible.']);
            return;
        }

        // Historique pour le contexte
        $history = $this->interactionModel->findByConversation($conversationId);
        $messages = [];
        foreach ($history as $it) {
            $messages[] = ['role' => 'user', 'content' => $it['prompt']];
            if ($it['response']) {
                $messages[] = ['role' => 'assistant', 'content' => $it['response']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        // System prompt si session
        $systemPrompt = null;
        if ($conversation['session_id']) {
            $sessionModel = new \App\Models\Session();
            $session = $sessionModel->find($conversation['session_id']);
            $systemPrompt = $session['system_prompt_override'] ?? null;
        }

        try {
            $result = $this->ollamaService->chatStream(
                $model['name'],
                $messages,
                $systemPrompt,
                fn(string $chunk) => $sendEvent('chunk', ['text' => $chunk])
            );

            // Sauvegarde de l'interaction complète
            $interactionId = $this->interactionModel->saveInteraction([
                'prompt'          => $prompt,
                'response'        => $result['response'],
                'latency'         => $result['latency'],
                'input_tokens'    => $result['input_tokens'],
                'output_tokens'   => $result['output_tokens'],
                'conversation_id' => $conversationId,
                'model_id'        => $modelId,
            ]);

            $sendEvent('done', [
                'interaction_id' => $interactionId,
                'latency'        => $result['latency'],
                'tokens'         => $result['input_tokens'] + $result['output_tokens'],
                'model'          => $model['name'],
            ]);
        } catch (\Exception $e) {
            $sendEvent('error', ['message' => 'Erreur du modèle : ' . $e->getMessage()]);
        }
    }

    /**
     * Vérifie le statut d'Ollama (endpoint AJAX pour l'indicateur).
     */
    public function ollamaStatus(): void
    {
        $this->requireAuth();
        $available = $this->ollamaService->isAvailable();
        $this->json([
            'online' => $available,
            'models' => $available ? count($this->ollamaService->listModels()) : 0,
        ]);
    }

    /**
     * Synchronise la table `model` avec les modèles Ollama, avec un cache
     * fichier de 5 minutes pour éviter d'interroger l'API à chaque page.
     */
    private function maybeSyncModels(): void
    {
        $cacheFile = sys_get_temp_dir() . '/iamu-ollama-sync.lock';
        $ttl = 300; // 5 minutes

        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            return;
        }

        try {
            if ($this->ollamaService->isAvailable()) {
                $this->llmModel->syncFromOllama($this->ollamaService);
            }
        } catch (\Exception $e) {
            // Échec silencieux : la sync est best-effort, le chat doit rester accessible.
            error_log('[Ollama sync] ' . $e->getMessage());
        }

        @touch($cacheFile);
    }
}
