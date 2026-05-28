<?php

namespace Controller;

use Core\controller;

class loginController extends controller
{
    /**
     * Affiche le formulaire de connexion.
     */
    public function showLogin(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/...'); #redrirection connection
        }
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
            $this->render('...', [
                'title' => 'Connexion',
                'error' => $result['error'],
                'email' => $email,
            ]);
            return;
        }
        $this->redirect('/...');
    }

    /**
     * Affiche le formulaire d'inscription.
     */
    public function showRegister(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/...');
        }
        $this->render('...', ['title' => 'Inscription']);
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
            'rgpd_consent'     => (bool) $this->input('rgpb_consent', false),
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

        $this->flash('success', 'Inscription réussie !');
        $this->redirect('/...');
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
