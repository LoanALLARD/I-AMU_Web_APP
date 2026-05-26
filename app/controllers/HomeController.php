<?php

namespace App\Controllers;

use App\Core\Controller;

class HomeController extends Controller
{
    public function index(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/chat');
        }
        // Page d'accueil pour les visiteurs non connectés
        $this->renderPartial('pages/landing', ['title' => 'Bienvenue']);
    }
}
