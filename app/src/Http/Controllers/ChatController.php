<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Core\Controller;

/**
 * Renders the authenticated chat home (the conversation shell). The
 * actual LLM round-trip is handled by {@see LLMController} on POST /chat.
 */
final class ChatController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $this->render('pages/homeView', [
            'user' => $this->currentUser(),
            'page' => 'chat',
        ], 'chat');
    }
}
