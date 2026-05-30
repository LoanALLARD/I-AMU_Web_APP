<?php

namespace Controllers;

use Core\Controller;
use Services\AuthService;

class LoginController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Displays the login form.
     */
    public function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/chat');
        }
        $this->render('Page/Auth/login', ['titrePage' => 'Connexion']);
    }

    /**
     * Processes the login form submission.
     */
    public function login(): void
    {
        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');

        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            $this->render('auth/login', [
                'titrePage' => 'Connexion',
                'error'     => $result['error'],
                'email'     => $email,
            ]);
            return;
        }

        $this->redirect('/chat');
    }

    /**
     * Displays the registration form.
     */
    public function showRegister(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/chat');
        }
        $this->render('Page/Auth/register', ['titrePage' => 'Inscription']);
    }

    public function showRGPD(): void
    {
        $this->render('Page/Auth/RGPDConsent', ['titrePage' => 'Mentions RGPD']);
    }

    /**
     * Processes the registration form submission.
     */
    public function register(): void
    {
        $data = [
            'email'            => trim($this->input('email', '')),
            'password'         => $this->input('password', ''),
            'password_confirm' => $this->input('password_confirm', ''),
            'first_name'       => trim($this->input('first_name', '')),
            'last_name'        => trim($this->input('last_name', '')),
            'rgpd_consent'     => (bool) $this->input('rgpd_consent', false),
        ];

        $result = $this->authService->register($data);

        if (!$result['success']) {
            $this->render('auth/register', [
                'titrePage' => 'Inscription',
                'error'     => $result['error'],
                'data'      => $data,
            ]);
            return;
        }

        $this->flash('success', 'Inscription reussie!');
        $this->redirect('/login');
    }

    /**
     * Destroys the session and redirects to login.
     */
    public function logout(): void
    {
        $this->authService->logout();
        $this->redirect('/login');
    }
}