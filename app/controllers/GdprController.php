<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;

class GdprController extends Controller
{
    /**
     * Affiche la page de consentement RGPD.
     */
    public function showConsent(): void
    {
        $this->requireAuth();
        $this->render('pages/auth/gdpr_consent', [
            'title' => 'Consentement RGPD',
            'user'  => $this->currentUser(),
        ]);
    }

    /**
     * Traite le choix de consentement.
     */
    public function handleConsent(): void
    {
        $this->requireAuth();
        $consent = (bool) $this->input('consent', false);

        $userModel = new User();
        $userModel->updateGdprConsent($_SESSION['user_id'], $consent);
        $_SESSION['gdpr_consent'] = $consent;

        if ($consent) {
            $this->redirect('/chat');
        } else {
            // Si refus, on bloque l'accès (selon les specs)
            $this->flash('warning', 'Vous devez accepter le traitement des données pour utiliser I-AMU.');
            $this->redirect('/gdpr/consent');
        }
    }
}
