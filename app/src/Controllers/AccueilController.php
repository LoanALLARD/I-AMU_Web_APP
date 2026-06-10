<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Services\ChatService;
use Services\DocumentService;
use Models\ConversationRepository;
use Models\InteractionRepository;
use Data\Database;
use Services\ExamLockService;

/**
 * Authenticated chat shell, routed from `/`, `/accueil`, `/chat` and
 * `/chat/{id}`.
 *
 * Resolves the current chat environment (session-bound or free) so the
 * sidebar lists the right conversations, then renders the chat home. A new
 * conversation is persisted on its first message (POST /chat), not up front.
 */
class AccueilController extends Controller
{
    private ChatService $chat;
    private ExamLockService $examLocks;

    public function __construct()
    {
        $this->chat = new ChatService(Database::getConnection());
        $this->examLocks = new ExamLockService(Database::getConnection());
    }

    public function index(?string $id = null): void
    {
        $this->requireAuth();
        $this->redirectNonChatRoles();
        $user = $this->currentUser();

        $conversationId = $id !== null && $id !== '' ? (int) $id : null;
        $archived  = $this->query('archived') === '1';

        // Exam lockdown: a student inside a running exam may only see their exam conversation.
        $examLock = $this->examLock();
        if ($examLock !== null && $examLock['conversationId'] !== null
            && $conversationId !== $examLock['conversationId']) {
            $this->redirect('/chat/' . $examLock['conversationId']);
        }

        $env            = $this->chat->environment((int) $user['id'], $conversationId, $archived);
        $models = [];
        try {
            $pdo = Database::getConnection();
            $aiRepository = new \Models\AiRepository($pdo);
            $sessionId = $env['env']['sessionId'] ?? null;

            if ($sessionId !== null) {
                // Session-bound chat: only teacher-authorized models.
                $models  = $aiRepository->findAllActiveBySession((int) $sessionId, null);
                $allowed = (new \Models\SessionRepository($pdo))->authorizedModelIdsOf((int) $sessionId);
                $models  = array_values(array_filter(
                    $models,
                    static fn ($m) => in_array((int) $m['id'], $allowed, true)
                ));
            } else {
                // Free chat: models of the user's department.
                $models = $aiRepository->findAllActiveBySession(null, (int) $user['department_id']);
            }
        } catch (\Throwable $e) {
            error_log('Impossible de charger les modèles : ' . $e->getMessage());
        }

        if (!empty($env['notFound'])) {
            $this->flash('error', 'Conversation introuvable.');
            $this->redirect('/chat');
        }
        $pdo = Database::getConnection();

        $conversationRepo = new ConversationRepository($pdo);
        $interactionRepo = new InteractionRepository($pdo);

        $conversation = null;
        $interactions = [];

        if ($conversationId !== null) {
            $conversation = $conversationRepo->getConversationByUserIdAndConversationId(
                $user['id'],
                (int) $conversationId
            );

            if ($conversation === null) {
                $this->flash('error', 'Conversation introuvable');
                $this->redirect('/chat');
            }
        }

        $conversations = array_map(
            static fn($v) => ['id' => $v['id'], 'name' => $v['name']],
            $conversationRepo->getConversationsByUserId($user['id'])
        );

        // Session documents (phase 1): shown read-only in the chat sidebar to
        // the enrolled students of a session-bound environment.
        $sessionDocuments = [];
        $envBlock = $env['env'] ?? null;
        if (is_array($envBlock) && isset($envBlock['sessionId']) && $envBlock['sessionId'] !== null) {
            $sessionDocuments = (new DocumentService($pdo))->listForSession((int) $envBlock['sessionId']);
        }
        // Conversation documents (phase 2), grouped by the message they were
        // sent with, so each appears under its message in the history.
        $messageDocuments = [];
        if ($conversationId !== null) {
            $messageDocuments = (new DocumentService($pdo))->documentsByInteractionForConversation((int) $conversationId);
        }

        // Whether/how the composer's document import is offered, per the session
        // settings (a free chat keeps the global defaults).
        $documentsConfig = (new DocumentService($pdo))->sessionDocumentsUiConfig(
            is_array($envBlock) && isset($envBlock['sessionId']) && $envBlock['sessionId'] !== null
                ? (int) $envBlock['sessionId']
                : null
        );

        $canAddModel = $_SESSION['isSpecialized'] ?? false;
        $this->render('pages/home', [
            'user' => $user,
            'canAddModel' => $canAddModel,
            'page' => 'chat',
            'models' => $models,
            'conversation' => $env['conversation'],
            'conversations' => $env['conversations'],
            'messages' => $env['messages'],
            'messageDocuments' => $messageDocuments,
            'documentsConfig' => $documentsConfig,
            'sessionClosed' => $env['sessionClosed'],
            'closedReason' => $env['closedReason'],
            'env' => $env['env'],
            'sessionDocuments' => $sessionDocuments,
            'archivedView' => $archived,
            'examLock'      => $examLock,
        ], 'chat');
    }

    /**
     * GET /chat/session-status — live poll for the read-only state of a session
     * conversation, so the chat page reacts to the teacher deactivating the
     * student (or closing the session) without a manual reload.
     */
    public function sessionStatus(): void
    {
        $this->requireAuth();
        $user   = $this->currentUser();
        $convId = (int) $this->query('conversation', 0);
        $status = $convId > 0
            ? $this->chat->sessionStatusFor((int) ($user['id'] ?? 0), $convId)
            : ['closed' => false, 'reason' => ''];

        $this->json($status);
    }

    /**
     * POST /chat/rename — rename a free-mode conversation. `current_id` is
     * the conversation currently open, used to return there afterwards.
     */
    public function renameChat(): void
    {
        $this->requireAuth();
        $this->redirectNonChatRoles();
        $this->verifyCsrf();
        $this->blockIfExamLocked();
        $user = $this->currentUser();

        $id = (int) $this->input('id', 0);
        $current = (int) $this->input('current_id', 0);
        $name = (string) $this->input('name', '');

        try {
            $this->chat->rename((int) $user['id'], $id, $name);
            $this->flash('success', 'Conversation renommée.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
        }

        $this->redirect($current > 0 ? '/chat/' . $current : '/chat');
    }

    /**
     * POST /chat/archive — archive a conversation. If the archived one was
     * open, land on the next conversation in the same environment (or free
     * chat when none remains); otherwise stay on the current conversation.
     */
    public function archiveChat(): void
    {
        $this->requireAuth();
        $this->redirectNonChatRoles();
        $this->verifyCsrf();
        $this->blockIfExamLocked();
        $user = $this->currentUser();

        $id = (int) $this->input('id', 0);
        $current = (int) $this->input('current_id', 0);

        try {
            $result = $this->chat->archive((int) $user['id'], $id);
            $this->flash('success', 'Conversation archivée.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect($current > 0 ? '/chat/' . $current : '/chat');
        }

        if ($current > 0 && $current !== $id) {
            $this->redirect('/chat/' . $current);
        }

        $this->redirect($result['next'] !== null ? '/chat/' . $result['next'] : '/chat');
    }

    /**
     * POST /chat/unarchive — restore an archived conversation and open it in
     * the active view.
     */
    public function unarchiveChat(): void
    {
        $this->requireAuth();
        $this->redirectNonChatRoles();
        $this->verifyCsrf();
        $this->blockIfExamLocked();
        $user = $this->currentUser();

        $id = (int) $this->input('id', 0);

        try {
            $this->chat->unarchive((int) $user['id'], $id);
            $this->flash('success', 'Conversation désarchivée.');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/chat');
        }

        $this->redirect('/chat/' . $id);
    }

    /** Sends roles with no chat access back to their own space. */
    private function redirectNonChatRoles(): void
    {
        if ($this->hasRole('department_admin')) {
            $this->redirect('/department-admin');
        }
        if ($this->hasRole('researcher')) {
            $this->redirect('/researcher');
        }
    }

    /**
     * Returns the active exam lock for the current student, or null. Only
     * students can be locked — teachers and admins monitor freely.
     *
     * @return array{sessionId:int, conversationId:?int, sessionName:string, endsAt:?string}|null
     */
    private function examLock(): ?array
    {
        if (!$this->hasRole('student')) {
            return null;
        }
        $user = $this->currentUser();

        return $this->examLocks->activeLockFor((int) ($user['id'] ?? 0));
    }

    /**
     * Rejects a chat mutation (new / rename / archive) while the student is
     * locked in a running exam, sending them back to the exam conversation.
     */
    private function blockIfExamLocked(): void
    {
        $lock = $this->examLock();
        if ($lock === null) {
            return;
        }
        $this->flash('error', 'Action indisponible pendant un examen.');
        $this->redirect($lock['conversationId'] !== null
            ? '/chat/' . $lock['conversationId']
            : '/chat');
    }
}
