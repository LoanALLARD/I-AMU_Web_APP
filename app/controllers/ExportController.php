<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Interaction;

class ExportController extends Controller
{
    /**
     * Exporte les données de recherche au format JSON.
     */
    public function exportJson(): void
    {
        $this->requireRole('researcher');

        $filters = [
            'session_id' => $this->query('session_id'),
            'from_date'  => $this->query('from'),
            'to_date'    => $this->query('to'),
        ];

        $interactionModel = new Interaction();
        $data = $interactionModel->exportForResearch(array_filter($filters));

        header('Content-Disposition: attachment; filename="iamu_export_' . date('Ymd_His') . '.json"');
        $this->json($data);
    }

    /**
     * Page d'export (interface chercheur).
     */
    public function index(): void
    {
        $this->requireRole('researcher');
        $this->render('pages/dashboard/export', [
            'title' => 'Export de données',
            'user'  => $this->currentUser(),
        ]);
    }
}
