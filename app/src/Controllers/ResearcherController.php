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
            array_filter(explode(',', (string) $this->query('departments', '')), static fn (string $s): bool => $s !== '')
        );

        $result = (new ResearcherAnalyticsService(Database::getConnection()))
            ->dashboard($researcherId, $ids);

        if (!$result['success']) {
            $this->json(['error' => $result['error']], 403);
        }

        $this->json($result['data']);
    }

    /** GET /researcher/export — pick a campus/department scope and export it. */
    public function export(): void
    {
        $this->requireRole('researcher');

        $researcherId = (int) $this->currentUser()['id'];

        $this->render('pages/researcher/export', [
            'titrePage' => 'Espace chercheur',
            'page'      => 'researcher',
            'user'      => $this->currentUser(),
            'places'    => (new ResearcherAuthorizationService(Database::getConnection()))
                ->listActiveGroupedByPlace($researcherId),
        ], 'chat');
    }

    /**
     * GET /researcher/export/download — streams the research corpus of the
     * chosen scope as JSON or CSV. `departments` is a comma-separated id list
     * (empty = the researcher's whole perimeter); `format` is json (default)
     * or csv. The corpus is consent-filtered and scope-validated in the service.
     */
    public function exportDownload(): void
    {
        $this->requireRole('researcher');

        $researcherId  = (int) $this->currentUser()['id'];
        $format        = $this->query('format') === 'csv' ? 'csv' : 'json';
        $departmentIds = array_values(array_map('intval', array_filter(
            explode(',', (string) $this->query('departments', '')),
            static fn (string $s): bool => $s !== ''
        )));

        $result = (new ResearcherAnalyticsService(Database::getConnection()))
            ->export($researcherId, $departmentIds);

        if (!$result['success']) {
            $this->flash('error', $result['error']);
            $this->redirect('/researcher/export');
        }

        $base = 'corpus-recherche-' . date('Ymd-His');

        if ($format === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $base . '.csv"');
            $out = fopen('php://output', 'w');
            if ($out === false) {
                exit;
            }
            // UTF-8 BOM so spreadsheet software reads the accents correctly.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'campus', 'departement', 'session_id', 'session', 'code_session',
                'etudiant_id', 'prenom', 'nom', 'email', 'numero_etudiant',
                'conversation_id', 'conversation', 'conversation_creee_le', 'archivee',
                'interaction_id', 'prompt', 'reponse', 'modele',
                'tokens_entree', 'tokens_sortie', 'latence_ms', 'feedback', 'envoye_le',
            ]);
            foreach ($result['rows'] as $r) {
                fputcsv($out, [
                    $r['campus'], $r['department'], $r['session_id'], $r['session_name'], $r['session_code'],
                    $r['student_id'], $r['first_name'], $r['last_name'], $r['email'], $r['student_number'],
                    $r['conversation_id'], $r['conversation_name'], $r['conversation_created_at'], $r['is_archived'] ? '1' : '0',
                    $r['interaction_id'], $r['prompt'], $r['response'], $r['model'],
                    $r['input_tokens'], $r['output_tokens'], $r['latency_ms'], $r['user_feedback'], $r['sent_at'],
                ]);
            }
            fclose($out);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $base . '.json"');
        echo json_encode([
            'exported_at'       => date('c'),
            'scope'             => $result['scope'],
            'interaction_count' => count($result['rows']),
            'interactions'      => $result['rows'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
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
