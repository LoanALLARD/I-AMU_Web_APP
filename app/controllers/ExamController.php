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

        $sid = (int) $sessionId;
        $session = $this->sessionModel->find($sid);
        if (!$session) {
            $this->redirect('/sessions');
            return;
        }

        $db = \App\Core\Database::getInstance();

        // Liste des étudiants connectés à la session + dernier prompt
        $rows = $db->query(
            "SELECT
                c.conversation_id,
                c.user_id,
                c.submitted_at,
                u.first_name,
                u.last_name,
                s.student_number,
                (SELECT i.prompt FROM interaction i
                   WHERE i.conversation_id = c.conversation_id
                   ORDER BY i.sent_at DESC LIMIT 1) AS last_prompt,
                (SELECT i.sent_at FROM interaction i
                   WHERE i.conversation_id = c.conversation_id
                   ORDER BY i.sent_at DESC LIMIT 1) AS last_at,
                (SELECT m.name FROM interaction i
                   JOIN model m ON m.model_id = i.model_id
                   WHERE i.conversation_id = c.conversation_id
                   ORDER BY i.sent_at DESC LIMIT 1) AS last_model,
                (SELECT COUNT(*) FROM interaction i WHERE i.conversation_id = c.conversation_id) AS prompt_count,
                (SELECT COUNT(*) FROM interaction i
                   WHERE i.conversation_id = c.conversation_id AND i.teacher_flag <> 0) AS flag_count
             FROM conversation c
             JOIN \"user\" u ON u.user_id = c.user_id
             LEFT JOIN student s ON s.user_id = c.user_id
             WHERE c.session_id = :sid
             ORDER BY u.last_name, u.first_name",
            ['sid' => $sid]
        )->fetchAll();

        // Conversation sélectionnée (?conversation=ID) ou la 1ʳᵉ si présent
        $selectedConvId = (int) ($_GET['conversation'] ?? 0);
        if (!$selectedConvId && !empty($rows)) {
            $selectedConvId = (int) $rows[0]['conversation_id'];
        }

        $selectedInteractions = [];
        $selectedRow = null;
        if ($selectedConvId) {
            $selectedRow = null;
            foreach ($rows as $r) {
                if ((int) $r['conversation_id'] === $selectedConvId) {
                    $selectedRow = $r;
                    break;
                }
            }
            $selectedInteractions = $db->query(
                "SELECT i.*, m.name AS model_name
                 FROM interaction i
                 JOIN model m ON m.model_id = i.model_id
                 WHERE i.conversation_id = :cid
                 ORDER BY i.sent_at ASC",
                ['cid' => $selectedConvId]
            )->fetchAll();
        }

        // Stats agrégées pour le header
        $stats = [
            'students'  => count($rows),
            'submitted' => array_filter($rows, fn($r) => $r['submitted_at'] !== null),
            'flagged'   => array_filter($rows, fn($r) => $r['flag_count'] > 0),
        ];

        $this->render('pages/exam/supervise', [
            'title'                => 'Supervision - ' . $session['name'],
            'session'              => $session,
            'students'             => $rows,
            'selectedConvId'       => $selectedConvId,
            'selectedRow'          => $selectedRow,
            'selectedInteractions' => $selectedInteractions,
            'stats'                => [
                'students_count'  => count($rows),
                'submitted_count' => count($stats['submitted']),
                'flagged_count'   => count($stats['flagged']),
            ],
            'user'                 => $this->currentUser(),
        ]);
    }

    /**
     * POST /exam/flag : signale un prompt et ajoute un commentaire enseignant.
     */
    public function flagPrompt(): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        $promptId = (int) $this->input('prompt_id');
        $reason   = trim($this->input('reason', ''));
        $comment  = trim($this->input('comment', ''));

        if (!$promptId) {
            $this->json(['error' => 'prompt_id requis'], 400);
            return;
        }

        \App\Core\Database::getInstance()->query(
            'UPDATE interaction
                SET teacher_flag = 1,
                    teacher_flag_reason = :reason,
                    teacher_comment = :comment
              WHERE prompt_id = :pid',
            ['reason' => $reason ?: null, 'comment' => $comment ?: null, 'pid' => $promptId]
        );

        $this->json(['success' => true]);
    }

    /**
     * POST /exam/{id}/archive : marque toutes les conversations comme rendues.
     */
    public function archiveSession(string $sessionId): void
    {
        $this->requireAnyRole(['teacher', 'admin']);

        \App\Core\Database::getInstance()->query(
            'UPDATE conversation
                SET submitted_at = CURRENT_TIMESTAMP, is_archived = TRUE
              WHERE session_id = :sid AND submitted_at IS NULL',
            ['sid' => (int) $sessionId]
        );

        $this->flash('success', 'Session archivée. Toutes les conversations sont marquées comme rendues.');
        $this->redirect('/exam/' . $sessionId . '/supervise');
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
