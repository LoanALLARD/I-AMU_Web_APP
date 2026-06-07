<?php

declare(strict_types=1);

namespace Controllers;

use Core\Controller;
use Data\Database;
use Models\PlaceRepository;
use Services\ResearcherAuthorizationService;

/**
 * Researcher space.
 *
 * Gated by requireRole('researcher'), which renders the 403 page for any
 * other visitor. Lets a researcher file an access request for a department
 * (one per department) and review the status of their existing requests;
 * department admins then process them from their own console.
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

    /**
     * POST /researcher/requests — files (or re-files) an access request for the
     * chosen department. One request per department: a repeat on a rejected or
     * revoked department resets it to pending.
     */
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
            $result['success'] ? 'Demande envoyee au departement.' : $result['error']
        );
        $this->redirect('/researcher');
    }
}
