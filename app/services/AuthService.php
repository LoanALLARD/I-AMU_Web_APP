<?php

namespace App\Services;

use App\Core\Application;
use App\Models\User;

/**
 * Service d'authentification.
 * Gère inscription, connexion, déconnexion et vérification de session.
 */
class AuthService
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Inscription d'un nouvel utilisateur.
     *
     * @return array{success: bool, error?: string, user_id?: int}
     */
    public function register(array $data): array
    {
        // Normalisation de l'email : les adresses sont case-insensitive,
        // on stocke en minuscules pour garantir l'unicité et faciliter les lookups.
        $data['email'] = strtolower($data['email'] ?? '');

        // Validation de l'email
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Adresse email invalide.'];
        }

        // Vérification du domaine email
        if (!$this->userModel->isAllowedDomain($data['email'])) {
            return ['success' => false, 'error' => 'Ce domaine email n\'est pas autorisé. Utilisez votre adresse universitaire.'];
        }

        // Vérification unicité
        if ($this->userModel->findByEmail($data['email'])) {
            return ['success' => false, 'error' => 'Un compte existe déjà avec cette adresse email.'];
        }

        // Validation mot de passe
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        if ($data['password'] !== ($data['password_confirm'] ?? '')) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }

        // Hachage du mot de passe
        $config = Application::getInstance()->getConfig('auth');
        $data['password_hash'] = password_hash(
            $data['password'],
            $config['password_algo'],
            ['cost' => $config['password_cost']]
        );

        try {
            $userId = $this->userModel->register($data);
            return ['success' => true, 'user_id' => $userId];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Erreur lors de l\'inscription : ' . $e->getMessage()];
        }
    }

    /**
     * Connexion d'un utilisateur.
     *
     * @return array{success: bool, error?: string}
     */
    public function login(string $email, string $password): array
    {
        // Les emails sont stockés en minuscules : on normalise pour matcher.
        $email = strtolower($email);

        $user = $this->userModel->findByEmail($email);

        if (!$user) {
            return ['success' => false, 'error' => 'Identifiants incorrects.'];
        }

        if (!$user['is_active']) {
            return ['success' => false, 'error' => 'Ce compte a été désactivé.'];
        }

        if (!password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Identifiants incorrects.'];
        }

        // Récupération des rôles
        $roles = $this->userModel->getRoles($user['user_id']);

        // Création de la session
        $_SESSION['user_id']         = $user['user_id'];
        $_SESSION['user_email']      = $user['email'];
        $_SESSION['user_first_name'] = $user['first_name'];
        $_SESSION['user_last_name']  = $user['last_name'];
        $_SESSION['roles']           = $roles;
        $_SESSION['gdpr_consent']    = (bool) $user['gdpr_consent'];

        // Mise à jour du dernier login
        $this->userModel->updateLastLogin($user['user_id']);

        // Régénération de l'ID de session pour éviter le fixation
        session_regenerate_id(true);

        return ['success' => true];
    }

    /**
     * Déconnexion.
     */
    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}
