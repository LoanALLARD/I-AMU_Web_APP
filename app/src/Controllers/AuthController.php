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
        $this->render('pages/auth/login', ['titrePage' => 'Connexion'], 'auth');
    }

    /**
     * Processes the login form submission.
     */
    public function login(): void
    {
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (str_contains($contentType, 'application/json')) {
            // Lecture JSON pour cURL
            $data = json_decode(file_get_contents('php://input'), true);
            $email    = isset($data['email']) ? trim($data['email']) : '';
            $password = isset($data['password']) ? $data['password'] : '';
        } else {
            // Lecture Formulaire pour le Web
            $email    = trim($this->input('email', ''));
            $password = $this->input('password', '');
        }

        $result = $this->authService->login($email, $password);

        if (!$result['success']) {
            $this->render('pages/auth/login', [
                'titrePage' => 'Connexion',
                'error'     => $result['error'],
                'email'     => $email,
                'deactivated' => !empty($result['deactivated']),],
                'auth');
            return;
        }

        // Each role lands on its own space; everyone else on the chat.
        if ($this->hasRole('department_admin')) {
            $this->redirect('/department-admin');
        }
        if ($this->hasRole('researcher')) {
            $this->redirect('/researcher');
        }
        $this->redirect('/chat');
    }

    public function reactivate(): void{

        $this->verifyCsrf();

        $email    = trim($this->input('email', ''));
        $password = $this->input('password', '');

        $result = $this->authService->reactivateAccount($email, $password);

        if (!$result['success']) {
            $this->render('pages/home', [
                'titrePage' => 'Connexion',
                'error'     => $result['error'],
                'email'     => $email,
            ], 'auth');
            return;
        }

        $loginResult = $this->authService->login($email, $password);

        if (!$loginResult['success']) {
            $this->render('pages/auth/login', [
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
        $this->render('pages/auth/register', [
            'titrePage' => 'Inscription',
            'places'    => $this->places->all()],
         'auth');
    }

    public function showRGPD(): void
    {
        $this->render('pages/auth/rgpd_consent', ['titrePage' => 'Mentions RGPD']);
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
            'is_researcher'    => (bool) $this->input('is_researcher', false),
            'rgpd_consent'     => (bool) $this->input('rgpd_consent', false),
        ];

        $result = $this->authService->register($data);

        if (empty($result['success'])) {
            $this->flash('error', $result['error'] ?? "Erreur lors de l'inscription.");
            $this->redirect('/register');
        }

        $this->flash('success', 'Inscription réussie ! Un email de vérification a été envoyé. Vérifiez votre boîte de réception.');
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

    public function verifyEmail(): void
    {
        $token = $this->query('token', '');

        if ($token === '') {
            $this->flash('error', 'Lien de vérification invalide.');
            $this->redirect('/login');
        }

        $pdo  = Database::getConnection();
        $users = new \Models\UserRepository($pdo);

        if ($users->verifyEmail($token)) {
            $this->flash('success', 'Votre email a été vérifié ! Vous pouvez vous connecter.');
        } else {
            $this->flash('error', 'Ce lien est invalide ou a déjà été utilisé.');
        }

        $this->redirect('/login');
    }

    public function showRGPDResearcher(): void
    {
        $this->render('pages/auth/rgpd_consent_researcher', ['titrePage' => 'Engagement chercheur — RGPD']);
    }

    /** Shows the acceptance form for a signed invitation link. */
    public function showAcceptInvite(): void
    {
        $token   = $this->query('token', '');
        $service = new \Services\AdminInviteService(Database::getConnection());
        $data    = $service->verifyToken($token);

        if ($data === null) {
            $this->flash('error', 'Lien invalide ou expiré.');
            $this->redirect('/login');
        }

        $this->render('pages/auth/accept-invite', [
            'titrePage' => 'Activer mon compte administrateur',
            'token'     => $token,
            'email'     => $data['email'],
        ], 'auth');
    }

    /** Creates the department-admin account from the invitation. */
    public function acceptInvite(): void
    {
        $this->verifyCsrf();

        $token   = (string) $this->input('token', '');
        $service = new \Services\AdminInviteService(Database::getConnection());

        // invited_by_id: a department_administrators row needs a super admin id.
        // Fall back to the first super admin when the link is opened logged-out.
        $pdo            = Database::getConnection();
        $invitedById    = (int) $pdo->query('SELECT id FROM super_administrators ORDER BY id LIMIT 1')->fetchColumn();

        $result = $service->accept(
            $token,
            (string) $this->input('password', ''),
            (string) $this->input('password_confirm', ''),
            (string) $this->input('first_name', ''),
            (string) $this->input('last_name', ''),
            $invitedById
        );

        if (!$result['success']) {
            $data = $service->verifyToken($token);
            $this->render('pages/auth/accept-invite', [
                'titrePage' => 'Activer mon compte administrateur',
                'token'     => $token,
                'email'     => $data['email'] ?? '',
                'error'     => $result['error'],
            ], 'auth');
            return;
        }

        $this->flash('success', 'Compte administrateur créé. Vous pouvez vous connecter.');
        $this->redirect('/login');
    }
}