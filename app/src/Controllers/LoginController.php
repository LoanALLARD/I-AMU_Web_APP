<?php

namespace Controllers;

use Core\Controller;
use Services\AuthService;

class LoginController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        // MVC wiring: the controller builds its own service, which gets the
        // shared connection from the Data\Database singleton. This matches
        // how public/index.php instantiates controllers (`new LoginController()`).
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
        $this->render('pages/Auth/login', ['titrePage' => 'Connexion'], 'auth');
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
            $this->render('pages/Auth/login', [
                'titrePage' => 'Connexion',
                'error'     => $result['error'],
                'email'     => $email,],
                'auth');
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
        $this->render('pages/Auth/register', [
            'titrePage' => 'Inscription'],
         'auth');
    }

    public function showRGPD(): void
    {
        $this->render('pages/Auth/RGPDConsent', ['titrePage' => 'Mentions RGPD']);
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
            $this->render('pages/Auth/register', [
                'titrePage' => 'Inscription',
                'error'=> $result['error'], 'data'=> $data,],
                'auth');
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