<?php

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Services\AuthService;

class AuthController extends Controller
{
    private AuthService $authService;
    private PlaceRepository $places;

    public function __construct()
    {
        // MVC wiring: the controller hands the shared Data\Database singleton
        // connection to the service it builds. This matches how
        // public/index.php instantiates controllers (`new AuthController()`).
        $pdo               = Database::getConnection();
        $this->authService = new AuthService($pdo);
        $this->places      = new PlaceRepository($pdo);
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
            'titrePage' => 'Inscription',
            'places'    => $this->places->all()],
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
            'place_id'         => $this->input('place_id', ''),
            'department_id'    => $this->input('department_id', ''),
            'rgpd_consent'     => (bool) $this->input('rgpd_consent', false),
        ];

        $result = $this->authService->register($data);

        if (!$result['success']) {
            $this->render('pages/Auth/register', [
                'titrePage' => 'Inscription',
                'error'=> $result['error'], 'data'=> $data,
                'places'=> $this->places->all(),],
                'auth');
            return;
        }

        // register() auto-logs-in the new user, so go straight to the app.
        $this->flash('success', 'Inscription reussie! Bienvenue.');
        $this->redirect('/chat');
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