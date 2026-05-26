<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuthService;

class LoginController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Affiche le formulaire de connexion.
     */
    public function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/chat');
        }
        $this->render('pages/auth/login', ['title' => 'Connexion']);
    }

    /**
     * Traite la soumission du formulaire de connexion.
     */
    public function login(): void
    {
        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');

        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            $this->render('pages/auth/login', [
                'title' => 'Connexion',
                'error' => $result['error'],
                'email' => $email,
            ]);
            return;
        }

        // Redirige vers le consentement RGPD si nécessaire
        if (empty($_SESSION['gdpr_consent'])) {
            $this->redirect('/gdpr/consent');
            return;
        }

        $this->redirect('/chat');
    }

    /**
     * Affiche le formulaire d'inscription.
     */
    public function showRegister(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/chat');
        }
        $this->render('pages/auth/register', ['title' => 'Inscription']);
    }

    /**
     * Traite la soumission du formulaire d'inscription.
     */
    public function register(): void
    {
        $data = [
            'email'            => trim($this->input('email', '')),
            'password'         => $this->input('password', ''),
            'password_confirm' => $this->input('password_confirm', ''),
            'first_name'       => trim($this->input('first_name', '')),
            'last_name'        => trim($this->input('last_name', '')),
            'student_number'   => trim($this->input('student_number', '')),
            'gdpr_consent'     => (bool) $this->input('gdpr_consent', false),
        ];

        $result = $this->authService->register($data);

        if (!$result['success']) {
            $this->render('pages/auth/register', [
                'title' => 'Inscription',
                'error' => $result['error'],
                'data'  => $data,
            ]);
            return;
        }

        $this->flash('success', 'Inscription réussie ! Vous pouvez maintenant vous connecter.');
        $this->redirect('/login');
    }

    /**
     * Déconnexion.
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('/login');
    }
}
