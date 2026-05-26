<?php

namespace App\Services;

use App\Core\Application;
use App\Models\PasswordReset;
use App\Models\User;

/**
 * Service d'authentification.
 * Gère inscription, connexion, déconnexion et vérification de session.
 */
class AuthService
{
    private User $userModel;
    private PasswordReset $passwordResetModel;

    public function __construct()
    {
        $this->userModel = new User();
        $this->passwordResetModel = new PasswordReset();
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
     * Demande de réinitialisation de mot de passe.
     *
     * Toujours retourne success=true côté contrôleur (message générique) pour
     * ne pas révéler l'existence d'un compte. Le jeton n'est généré que si
     * l'email correspond à un compte actif, et le lien est envoyé par mail.
     * En debug, l'URL est également remontée pour faciliter les tests.
     *
     * @return array{token?: string, debug_url?: string, mail_sent?: bool}
     */
    public function requestPasswordReset(string $email, string $resetUrlBase): array
    {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [];
        }

        $user = $this->userModel->findByEmail($email);
        if (!$user || empty($user['is_active'])) {
            return [];
        }

        $token = $this->passwordResetModel->createForUser((int) $user['user_id']);
        $url   = rtrim($resetUrlBase, '/') . '?token=' . urlencode($token);

        // Envoi du mail via le SMTP configuré (maildev en dev).
        $mailer = new Mailer();
        $sent = $mailer->sendPasswordReset($email, $url);

        $appConfig = Application::getInstance()->getConfig('app');
        $result = ['token' => $token, 'mail_sent' => $sent];
        if (!empty($appConfig['debug'])) {
            $result['debug_url'] = $url;
        }
        return $result;
    }

    /**
     * Indique si un jeton de reset est encore valide (non expiré, non consommé).
     */
    public function isResetTokenValid(string $token): bool
    {
        return $this->passwordResetModel->findValidByToken($token) !== null;
    }

    /**
     * Applique un nouveau mot de passe à partir d'un jeton de reset valide.
     *
     * @return array{success: bool, error?: string}
     */
    public function performPasswordReset(string $token, string $newPassword, string $confirm): array
    {
        $row = $this->passwordResetModel->findValidByToken($token);
        if (!$row) {
            return ['success' => false, 'error' => 'Lien de réinitialisation invalide ou expiré.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'error' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }
        if ($newPassword !== $confirm) {
            return ['success' => false, 'error' => 'Les mots de passe ne correspondent pas.'];
        }

        $config = Application::getInstance()->getConfig('auth');
        $hash = password_hash(
            $newPassword,
            $config['password_algo'],
            ['cost' => $config['password_cost']]
        );

        $this->userModel->update((int) $row['user_id'], ['password_hash' => $hash]);
        $this->passwordResetModel->markUsed((int) $row['reset_id']);

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
