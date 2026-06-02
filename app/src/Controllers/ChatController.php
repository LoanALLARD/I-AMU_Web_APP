<?php

namespace Controllers;

use Core\Controller;

class ChatController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $user = $this->currentUser();

        $this->render('pages/homeView', [
            'user' => $user,
            'page' => 'chat',
        ], 'chat');
    }
}