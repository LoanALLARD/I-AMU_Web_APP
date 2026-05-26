<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Session;
use App\Models\Conversation;
use App\Models\Interaction;
use App\Models\LlmModel;
use App\Services\OllamaService;

/**
 * Contrôleur pour le mode examen.
 * Interface verrouillée : pas d'historique, pas de changement de mode,
 * seuls les modèles autorisés sont accessibles.
 */
class ExamController extends Controller
{
    private Session $sessionModel;
    private Conversation $conversationModel;
    private Interaction $interactionModel;
    private OllamaService $ollamaService;

    public function __construct()
    {
        $this->sessionModel      = new Session();
        $this->conversationModel = new Conversation();
        $this->interactionModel  = new Interaction();
        $this->ollamaService     = new OllamaService();
    }

    /**
     * Affiche l'interface d'examen verrouillée.
     */
    public function show(string $conversationId): void
    {
        $this->requireAuth();

        $conversation = $this->conversationModel->find((int) $conversationId);
        if (!$conversation || $conversation['user_id'] !== $_SESSION['user_id']) {
            $this->redirect('/chat');
            return;
        }

        // Vérification que c'est bien une conversation d'examen
        if ($conversation['type'] !== 'EXAM') {
            $this->redirect('/chat/' . $conversationId);
            return;
        }

        $session = $this->sessionModel->find($conversation['session_id']);
        if (!$session) {
            $this->redirect('/chat');
            return;
        }

        // Vérification que l'examen est toujours actif
        if (!$this->sessionModel->isActive($session['session_id'])) {
            $this->render('pages/exam/ended', [
                'title'   => 'Examen terminé',
                'session' => $session,
                'user'    => $this->currentUser(),
            ], 'exam');
            return;
        }

        // Modèles autorisés pour cette session uniquement
        $models = $this->sessionModel->getAuthorizedModels($session['session_id']);
        $interactions = $this->interactionModel->findByConversation((int) $conversationId);

        // Temps restant
        $endsAt = strtotime($session['ends_at']);
        $remainingSeconds = max(0, $endsAt - time());

        $this->render('pages/exam/index', [
            'title'            => 'Examen - ' . $session['name'],
            'session'          => $session,
            'conversation'     => $conversation,
            'interactions'     => $interactions,
            'models'           => $models,
            'remainingSeconds' => $remainingSeconds,
            'maxInputSize'     => $session['max_input_size'] ?? 2000,
            'instructions'     => $session['instructions'] ?? '',
            'user'             => $this->currentUser(),
        ], 'exam');
    }

    /**
     * Envoie un prompt en mode examen (avec vérifications supplémentaires).
     */
    public function sendPrompt(): void
    {
        $this->requireAuth();

        $conversationId = (int) $this->input('conversation_id');
        $modelId        = (int) $this->input('model_id');
        $prompt         = trim($this->input('prompt', ''));

        // Vérification conversation
        $conversation = $this->conversationModel->find($conversationId);
        if (!$conversation || $conversation['user_id'] !== $_SESSION['user_id'] || $conversation['type'] !== 'EXAM') {
            $this->json(['error' => 'Accès refusé.'], 403);
            return;
        }

        // Vérification session active
        $session = $this->sessionModel->find($conversation['session_id']);
        if (!$session || !$this->sessionModel->isActive($session['session_id'])) {
            $this->json(['error' => 'L\'examen est terminé.'], 403);
            return;
        }

        // Vérification taille du prompt
        $maxSize = $session['max_input_size'] ?? 2000;
        if (mb_strlen($prompt) > $maxSize) {
            $this->json(['error' => "Le prompt dépasse la limite de $maxSize caractères."], 400);
            return;
        }

        // Vérification que le modèle est autorisé
        $authorizedModels = $this->sessionModel->getAuthorizedModels($session['session_id']);
        $modelIds = array_column($authorizedModels, 'model_id');
        if (!in_array($modelId, $modelIds)) {
            $this->json(['error' => 'Ce modèle n\'est pas autorisé pour cet examen.'], 403);
            return;
        }

        $llmModel = new LlmModel();
        $model = $llmModel->find($modelId);

        // Construction de l'historique
        $history = $this->interactionModel->findByConversation($conversationId);
        $messages = [];
        foreach ($history as $interaction) {
            $messages[] = ['role' => 'user', 'content' => $interaction['prompt']];
            if ($interaction['response']) {
                $messages[] = ['role' => 'assistant', 'content' => $interaction['response']];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];

        $systemPrompt = $session['system_prompt_override'] ?? null;

        try {
            $result = $this->ollamaService->chat($model['name'], $messages, $systemPrompt);

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
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => 'Erreur du modèle : ' . $e->getMessage()], 500);
        }
    }

    /**
     * Supervision d'examen par l'enseignant : prompts en temps réel.
     */
    public function supervise(string $sessionId): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $session = $this->sessionModel->find((int) $sessionId);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        $conversations = $this->conversationModel->findBySession((int) $sessionId);

        // Récupérer toutes les interactions de toutes les conversations de l'examen
        $allInteractions = [];
        foreach ($conversations as $conv) {
            $interactions = $this->interactionModel->findByConversation($conv['conversation_id']);
            foreach ($interactions as $interaction) {
                $interaction['conversation_name'] = $conv['name'];
                $interaction['student_user_id'] = $conv['user_id'];
                $allInteractions[] = $interaction;
            }
        }

        // Tri par date
        usort($allInteractions, fn($a, $b) => strtotime($a['sent_at']) - strtotime($b['sent_at']));

        $this->render('pages/exam/supervise', [
            'title'           => 'Supervision - ' . $session['name'],
            'session'         => $session,
            'conversations'   => $conversations,
            'allInteractions' => $allInteractions,
            'user'            => $this->currentUser(),
        ]);
    }

    /**
     * Endpoint AJAX pour récupérer les nouveaux prompts (polling enseignant).
     */
    public function pollInteractions(string $sessionId): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $afterId = (int) $this->query('after_id', 0);
        $session = $this->sessionModel->find((int) $sessionId);

        if (!$session) {
            $this->json(['error' => 'Session introuvable.'], 404);
            return;
        }

        $db = \App\Core\Database::getInstance();
        $sql = "SELECT i.*, c.user_id AS student_user_id, u.first_name, u.last_name
                FROM interaction i
                JOIN conversation c ON i.conversation_id = c.conversation_id
                JOIN \"user\" u ON c.user_id = u.user_id
                WHERE c.session_id = :sid AND i.prompt_id > :after_id
                ORDER BY i.sent_at ASC";

        $interactions = $db->query($sql, ['sid' => (int) $sessionId, 'after_id' => $afterId])->fetchAll();

        $this->json(['interactions' => $interactions]);
    }
}
