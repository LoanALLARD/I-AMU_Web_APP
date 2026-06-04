<?php

namespace Controllers;

namespace App\Http\Controllers;

use App\Domain\Repositories\SessionRepositoryInterface;
use App\Domain\ValueObjects\SessionStatus;
use Core\Controller;
use Models\ConversationRepository;
use Models\AIRepository;

class ChatController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $models = [];
        try {
            $pdo = Database::getConnection();
            $aiRepository = new AiRepository($pdo);
            $models = $aiRepository->findAllActive();
        } catch (\Throwable $e) {
            error_log('Impossible de charger les modèles : ' . $e->getMessage());
        }

        $conversations = array_map(
            static fn ($v) => ['id' => $v['id'], 'name' => $v['name']],
            $this->conversations->getConversationsByUserId($user['id'])
        );

        $this->render('pages/homeView', [
            'user'          => $user,
            'page'          => 'chat',
            'conversation'  => $conversation,
            'conversations' => $conversations,
            'sessionClosed' => $sessionClosed,
            'closedReason'  => $closedReason,
            'models'        => $models,
        ], 'chat');
    }
}