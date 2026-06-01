<?php

namespace Controllers;

use Core\Controller;

class ChatController extends Controller
{
    public function index(): void
    {
        $user = $this->currentUser();

        $this->render('Page/homeView', [
            'titrePage' => 'Chat',
            'user'      => $user],
            'chat');
    }
}