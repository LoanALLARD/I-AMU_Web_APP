<?php

namespace Controllers;

use Core\Controller;
use Services\AuthService;

class LoginController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
    ) {
        // AuthService is injected from public/index.php where the DI graph
        // is assembled (PdoConnection -> AuthService -> LoginController).
        // Do NOT swap this back to a no-arg constructor + `new AuthService()`
        // — AuthService now requires a PdoConnection to talk to the users
        // table, so a no-arg new throws ArgumentCountError at first request.
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
                'email'     => $email,
                'deactivated' => !empty($result['deactivated']),],
                'auth');
            return;
        }

        $this->redirect('/chat');
    }

    public function reactivate(): void{

        $this->verifyCsrf();

        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');

        $result = $this->authService->reactivateAccount($email, $password);

        if (!$result['success']) {
            $this->render('pages/homeView', [
                'titrePage' => 'Connexion',
                'error'     => $result['error'],
                'email'     => $email,
            ], 'auth');
            return;
        }

        $loginResult = $this->authService->login($email, $password);

        if (!$loginResult['success']) {
            $this->render('pages/Auth/login', [
                'titrePage' => 'Connexion',
                'error'     => $loginResult['error'],
                'email'     => $email,
            ], 'auth');
            return;
        }

        $this->flash('success', 'Votre compte a été réactivé.');
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