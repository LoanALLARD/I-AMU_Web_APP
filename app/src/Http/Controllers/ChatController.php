<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Ports\ModelReadRepositoryInterface;
use Core\Controller;

/**
 * Renders the authenticated chat home (the conversation shell). The
 * actual LLM round-trip is handled by {@see LLMController} on POST /chat.
 */
final class ChatController extends Controller
{
    public function __construct(
        private readonly ModelReadRepositoryInterface $models,
    ) {
    }

    public function index(): void
    {
        $this->requireAuth();
        $activeModels = $this->models->findAllActive();
        $this->render('pages/homeView', [
            'user'   => $this->currentUser(),
            'page'   => 'chat',
            'models' => $activeModels,
        ], 'chat');
    }

}
