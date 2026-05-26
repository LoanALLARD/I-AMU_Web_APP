<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Application;
use App\Models\User;

class AccountController extends Controller
{
    private User $userModel;

    public function __construct()
    {
        $this->userModel = new User();
    }

    /**
     * Page de gestion du compte.
     */
    public function index(): void
    {
        $this->requireAuth();

        $user = $this->userModel->find($_SESSION['user_id']);
        $roles = $this->userModel->getRoles($_SESSION['user_id']);

        $this->render('pages/account/index', [
            'title'    => 'Mon compte',
            'account'  => $user,
            'roles'    => $roles,
            'user'     => $this->currentUser(),
        ]);
    }

    /**
     * Met à jour le profil utilisateur.
     */
    public function updateProfile(): void
    {
        $this->requireAuth();

        $firstName = trim($this->input('first_name', ''));
        $lastName  = trim($this->input('last_name', ''));

        if (empty($firstName) || empty($lastName)) {
            $this->flash('error', 'Le prénom et le nom sont obligatoires.');
            $this->redirect('/account');
            return;
        }

        $this->userModel->update($_SESSION['user_id'], [
            'first_name' => $firstName,
            'last_name'  => $lastName,
        ]);

        $_SESSION['user_first_name'] = $firstName;
        $_SESSION['user_last_name']  = $lastName;

        $this->flash('success', 'Profil mis à jour.');
        $this->redirect('/account');
    }

    /**
     * Change le mot de passe.
     */
    public function changePassword(): void
    {
        $this->requireAuth();

        $current  = $this->input('current_password', '');
        $new      = $this->input('new_password', '');
        $confirm  = $this->input('confirm_password', '');

        // Vérification du mot de passe actuel
        $user = $this->userModel->find($_SESSION['user_id']);
        if (!password_verify($current, $user['password_hash'])) {
            $this->flash('error', 'Mot de passe actuel incorrect.');
            $this->redirect('/account');
            return;
        }

        if (strlen($new) < 8) {
            $this->flash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
            $this->redirect('/account');
            return;
        }

        if ($new !== $confirm) {
            $this->flash('error', 'Les mots de passe ne correspondent pas.');
            $this->redirect('/account');
            return;
        }

        $config = Application::getInstance()->getConfig('auth');
        $hash = password_hash($new, $config['password_algo'], ['cost' => $config['password_cost']]);

        $this->userModel->update($_SESSION['user_id'], ['password_hash' => $hash]);

        $this->flash('success', 'Mot de passe modifié avec succès.');
        $this->redirect('/account');
    }

    /**
     * Retire le consentement RGPD.
     */
    public function revokeConsent(): void
    {
        $this->requireAuth();

        $this->userModel->updateGdprConsent($_SESSION['user_id'], false);
        $_SESSION['gdpr_consent'] = false;

        $this->flash('warning', 'Consentement retiré. Vous n\'avez plus accès à la plateforme.');
        $this->redirect('/gdpr/consent');
    }

    /**
     * Suppression du compte.
     */
    public function deleteAccount(): void
    {
        $this->requireAuth();

        $password = $this->input('delete_password', '');
        $user = $this->userModel->find($_SESSION['user_id']);

        if (!password_verify($password, $user['password_hash'])) {
            $this->flash('error', 'Mot de passe incorrect. Suppression annulée.');
            $this->redirect('/account');
            return;
        }

        // Désactivation du compte (soft delete)
        $this->userModel->update($_SESSION['user_id'], ['is_active' => false]);

        // Déconnexion
        $authService = new \App\Services\AuthService();
        $authService->logout();

        $this->redirect('/login');
    }
}
