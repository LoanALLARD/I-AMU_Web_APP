<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;

/**
 * Researcher landing.
 *
 * Gated by requireRole('researcher'), which renders the 403 page for any
 * other visitor.
 */
class ResearcherController extends Controller
{
    public function index(): void
    {
        $this->requireRole('researcher');

        $this->render('pages/researcher/dashboard', [
            'titrePage' => 'Espace chercheur',
            'user'      => $this->currentUser(),
        ]);
    }
}
