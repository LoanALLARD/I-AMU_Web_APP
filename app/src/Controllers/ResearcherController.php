<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Services\ResearcherAnalyticsService;
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
            'page'      => 'researcher',
            'user'      => $this->currentUser(),
            'places'    => (new PlaceRepository($pdo))->all(),
            'requests'  => (new ResearcherAuthorizationService($pdo))->listForResearcher($researcherId),
        ], 'chat');
    }

    /** GET /researcher/data — browse and analyse accessible department data. */
    public function data(): void
    {
        $this->requireRole('researcher');

        $researcherId = (int) $this->currentUser()['id'];

        $this->render('pages/researcher/data', [
            'titrePage' => 'Espace chercheur',
            'page'      => 'researcher',
            'user'      => $this->currentUser(),
            'places'    => (new ResearcherAuthorizationService(Database::getConnection()))
                ->listActiveGroupedByPlace($researcherId),
        ], 'chat');
    }

    /** GET /researcher/data/stats — JSON dashboard for the scope (comma-separated department ids). */
    public function stats(): void
    {
        $this->requireRole('researcher');

        $researcherId = (int) $this->currentUser()['id'];
        $ids = array_map(
            'intval',
            array_filter(explode(',', (string) $this->query('departments', '')), 'strlen')
        );

        $result = (new ResearcherAnalyticsService(Database::getConnection()))
            ->dashboard($researcherId, $ids);

        if (!$result['success']) {
            $this->json(['error' => $result['error']], 403);
        }

        $this->json($result['data']);
    }

    /** GET /researcher/export — export accessible department data. */
    public function export(): void
    {
        $this->requireRole('researcher');

        $this->render('pages/researcher/export', [
            'titrePage' => 'Espace chercheur',
            'page'      => 'researcher',
            'user'      => $this->currentUser(),
        ], 'chat');
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
