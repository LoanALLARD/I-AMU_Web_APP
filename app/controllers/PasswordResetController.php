<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AuthService;

/**
 * Flow "Mot de passe oublié".
 *
 * 1. /password/forgot (GET)  → formulaire email
 * 2. /password/forgot (POST) → génère un jeton, retourne un message générique
 * 3. /password/reset  (GET)  → formulaire nouveau mot de passe (token en query)
 * 4. /password/reset  (POST) → applique le nouveau mot de passe
 */
class PasswordResetController extends Controller
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    /**
     * Affiche le formulaire de demande de réinitialisation.
     */
    public function showForgot(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/account');
            return;
        }
        $this->render('pages/auth/forgot', ['title' => 'Mot de passe oublié']);
    }

    /**
     * Traite la demande de réinitialisation.
     * Toujours afficher un message générique pour ne pas révéler l'existence
     * d'un compte associé à l'email saisi.
     */
    public function requestReset(): void
    {
        $email = trim($this->input('email', ''));
        $resetUrlBase = $this->absoluteUrl('/password/reset');

        $result = $this->authService->requestPasswordReset($email, $resetUrlBase);

        $this->render('pages/auth/forgot', [
            'title'       => 'Mot de passe oublié',
            'submitted'   => true,
            // Message générique côté UI — pas d'indication sur l'existence du compte.
            'genericInfo' => "Si un compte existe pour cette adresse, un lien de réinitialisation a été envoyé.",
            // En debug uniquement, l'URL est exposée directement pour faciliter
            // le test sans serveur SMTP configuré.
            'debugUrl'    => $result['debug_url'] ?? null,
        ]);
    }

    /**
     * Affiche le formulaire de nouveau mot de passe. Valide le jeton avant
     * d'afficher quoi que ce soit d'utile.
     */
    public function showReset(): void
    {
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/account');
            return;
        }
        // Le token arrive en query string (lien cliqué depuis l'email).
        $token = (string) $this->query('token', '');
        $valid = $token !== '' && $this->authService->isResetTokenValid($token);

        $this->render('pages/auth/reset', [
            'title' => 'Nouveau mot de passe',
            'token' => $token,
            'valid' => $valid,
        ]);
    }

    /**
     * Applique le nouveau mot de passe.
     */
    public function performReset(): void
    {
        $token   = (string) $this->input('token', '');
        $new     = (string) $this->input('new_password', '');
        $confirm = (string) $this->input('confirm_password', '');

        $result = $this->authService->performPasswordReset($token, $new, $confirm);

        if (!$result['success']) {
            $this->render('pages/auth/reset', [
                'title' => 'Nouveau mot de passe',
                'token' => $token,
                'valid' => true, // évite de masquer le form sur erreur de validation
                'error' => $result['error'],
            ]);
            return;
        }

        $this->flash('success', 'Mot de passe mis à jour. Vous pouvez vous connecter.');
        $this->redirect('/login');
    }

    /**
     * Construit une URL absolue à partir d'un chemin, basée sur la config app.url.
     */
    private function absoluteUrl(string $path): string
    {
        $appConfig = \App\Core\Application::getInstance()->getConfig('app');
        $base = rtrim($appConfig['url'] ?? '', '/');
        return $base . $path;
    }
}
