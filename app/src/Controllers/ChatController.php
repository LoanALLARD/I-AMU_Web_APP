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

        $pdo = Database::getConnection();
        $aiRepository = new AiRepository($pdo);
        $models = $aiRepository->getAllActiveModels();


        $this->render('Page/homeView', [
            'titrePage' => 'Chat',
            'user'      => $user,
            'models'    => $models,],
            'chat');
    }
}