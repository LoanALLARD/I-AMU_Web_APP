<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Services\ChatService;
use Models\ConversationRepository;
use Models\InteractionRepository;
use Data\Database;

/**
 * Authenticated chat shell, routed from `/`, `/accueil`, `/chat` and
 * `/chat/{id}`.
 *
 * Resolves the current chat environment (session-bound or free) so the
 * sidebar lists the right conversations, then renders the chat home. New
 * conversations are created via POST /chat/new.
 */
class AccueilController extends Controller
{
    private ChatService $chat;

    public function __construct()
    {
        $this->chat = new ChatService(Database::getConnection());
    }

    public function index(?string $id = null): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $conversationId = $id !== null && $id !== '' ? (int) $id : null;
        $archived       = $this->query('archived') === '1';
        $env            = $this->chat->environment((int) $user['id'], $conversationId, $archived);

        if (!empty($env['notFound'])) {
            $this->flash('error', 'Conversation introuvable.');
            $this->redirect('/chat');
        }
        $pdo = Database::getConnection();

        $conversationRepo = new ConversationRepository($pdo);
        $interactionRepo   = new InteractionRepository($pdo);

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
            static fn ($v) => ['id' => $v['id'], 'name' => $v['name']],
            $conversationRepo->getConversationsByUserId($user['id'])
        );

        $this->render('pages/homeView', [
            'user'          => $user,
            'page'          => 'chat',
            'conversation'  => $env['conversation'],
            'conversations' => $env['conversations'],
            'messages'  => $env['messages'],
            'sessionClosed' => $env['sessionClosed'],
            'closedReason'  => $env['closedReason'],
            'env'           => $env['env'],
            'archivedView'  => $archived,
        ], 'chat');
    }

    /**
     * POST /chat/new — create a conversation in the current environment.
     * A `session_id` field means a session conversation; absent means free.
     */
    public function newChat(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $sessionId = (int) $this->input('session_id', 0);

        try {
            $conversationId = $sessionId > 0
                ? $this->chat->newSessionConversation((int) $user['id'], $sessionId)
                : $this->chat->newFreeConversation((int) $user['id']);
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('/chat');
        }

        $this->redirect('/chat/' . $conversationId);
    }

    /**
     * POST /chat/rename — rename a free-mode conversation. `current_id` is
     * the conversation currently open, used to return there afterwards.
     */
    public function renameChat(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $user = $this->currentUser();

        $id      = (int) $this->input('id', 0);
        $current = (int) $this->input('current_id', 0);
        $name    = (string) $this->input('name', '');

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
        $this->verifyCsrf();
        $user = $this->currentUser();

        $id      = (int) $this->input('id', 0);
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
        $this->verifyCsrf();
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
}
