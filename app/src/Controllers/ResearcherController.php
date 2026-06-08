<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Services\ResearcherAuthorizationService;

/**
 * Researcher space: file and track department access requests.
 * Every action is gated by requireRole('researcher').
 */
class ResearcherController extends Controller
{
    public function index(): void
    {
        $this->requireRole('researcher');

        $pdo = Database::getConnection();
        $researcherId = (int) $this->currentUser()['id'];

        $this->render('pages/researcher/dashboard', [
            'titrePage' => 'Espace chercheur',
            'user'      => $this->currentUser(),
            'places'    => (new PlaceRepository($pdo))->all(),
            'requests'  => (new ResearcherAuthorizationService($pdo))->listForResearcher($researcherId),
        ]);
    }

    /** GET /researcher/data — browse and export accessible department data. */
    public function data(): void
    {
        $this->requireRole('researcher');

        $this->render('pages/researcher/data', [
            'titrePage' => 'Espace chercheur',
            'user'      => $this->currentUser(),
        ]);
    }

    /** POST /researcher/requests — files (or re-files) an access request. */
    public function requestAccess(): void
    {
        $this->requireRole('researcher');
        $this->verifyCsrf();

        $researcherId = (int) $this->currentUser()['id'];
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->requestAccess(
                $researcherId,
                (int) $this->input('place_id', 0),
                (int) $this->input('department_id', 0),
                (string) $this->input('request', '')
            );

        $this->flash(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande envoyée au département.' : $result['error']
        );
        $this->redirect('/researcher');
    }

    /** POST /researcher/requests/cancel — cancels a still-pending request. */
    public function cancelRequest(): void
    {
        $this->requireRole('researcher');
        $this->verifyCsrf();

        $researcherId = (int) $this->currentUser()['id'];
        $result = (new ResearcherAuthorizationService(Database::getConnection()))
            ->cancelRequest($researcherId, (int) $this->input('department_id', 0));

        $this->flash(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Demande annulée.' : $result['error']
        );
        $this->redirect('/researcher');
    }
}
