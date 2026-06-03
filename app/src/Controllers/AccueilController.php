<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Home page (authenticated chat shell). Routed from `/` and `/accueil`.
 *
 * Minimal MVC controller: it guards auth and renders the chat home inside
 * Layout/chat.php. The conversation history and live LLM round-trip belong
 * to the Chat/LLM feature (ChatController / LLMController) — to be migrated
 * to MVC separately; the layout degrades gracefully without that data.
 */
class AccueilController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $this->render('pages/homeView', [
            'user'          => $this->currentUser(),
            'page'          => 'chat',
            'conversation'  => null,
            'conversations' => [],
            'sessionClosed' => false,
            'closedReason'  => '',
        ], 'chat');
    }
}
