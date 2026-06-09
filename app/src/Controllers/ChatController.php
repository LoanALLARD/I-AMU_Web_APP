<?php

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\AiRepository;

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

        $this->render('Page/homeView', [
            'titrePage' => 'Chat',
            'user'      => $user,
            'models'    => $models,
        ], 'chat');
    }
}