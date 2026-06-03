<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Services\ChatService;

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
        $env            = $this->chat->environment((int) $user['id'], $conversationId);

        if (!empty($env['notFound'])) {
            $this->flash('error', 'Conversation introuvable.');
            $this->redirect('/chat');
        }

        $this->render('pages/homeView', [
            'user'          => $user,
            'page'          => 'chat',
            'conversation'  => $env['conversation'],
            'conversations' => $env['conversations'],
            'sessionClosed' => $env['sessionClosed'],
            'closedReason'  => $env['closedReason'],
            'env'           => $env['env'],
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
}
