<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Interaction;

class ExportController extends Controller
{
    /**
     * Dashboard chercheur : filtres + agrégations (volumes, longueurs, sujets).
     * Toutes les agrégations sont calculées en SQL pour rester rapides
     * même sur de gros corpus.
     */
    public function index(): void
    {
        $this->requireRole('researcher');

        $db = Database::getInstance();

        // ─── Filtres ───────────────────────────────────────────────
        $from        = $this->query('from')   ?: date('Y-m-d', strtotime('-6 months'));
        $to          = $this->query('to')     ?: date('Y-m-d');
        $resourceIds = (array) ($this->query('resource') ?? []);
        $modelName   = $this->query('model', '');
        $role        = $this->query('role', '');
        $minTokens   = (int) $this->query('min_tokens', 0);
        $anonymised  = $this->query('anonymised', '1') === '1';

        // ─── Conditions WHERE communes ─────────────────────────────
        $where  = ['i.sent_at >= :from_date', 'i.sent_at < (:to_date::date + 1)'];
        $params = ['from_date' => $from, 'to_date' => $to];

        if (!empty($resourceIds)) {
            $placeholders = [];
            foreach ($resourceIds as $i => $rid) {
                $k = 'rid' . $i;
                $placeholders[] = ':' . $k;
                $params[$k] = (int) $rid;
            }
            $where[] = 's.resource_id IN (' . implode(',', $placeholders) . ')';
        }

        if ($modelName !== '' && $modelName !== 'all') {
            $where[] = 'm.name = :model_name';
            $params['model_name'] = $modelName;
        }

        if ($minTokens > 0) {
            $where[] = 'i.input_tokens >= :min_tokens';
            $params['min_tokens'] = $minTokens;
        }

        // Si rôle = étudiant : restreint aux conversations d'étudiants
        if ($role === 'student') {
            $where[] = 'EXISTS (SELECT 1 FROM student st WHERE st.user_id = c.user_id)';
        } elseif ($role === 'teacher') {
            $where[] = 'EXISTS (SELECT 1 FROM teacher t WHERE t.user_id = c.user_id)';
        }

        $whereSql = implode(' AND ', $where);

        $baseFrom = "FROM interaction i
            JOIN conversation c ON c.conversation_id = i.conversation_id
            JOIN model m        ON m.model_id        = i.model_id
            LEFT JOIN session s ON s.session_id      = c.session_id
            WHERE $whereSql";

        // ─── Compteur total ────────────────────────────────────────
        $total = (int) $db->query(
            "SELECT COUNT(*)::int AS n $baseFrom", $params
        )->fetch()['n'];

        // ─── Volume par cours (top 12) ─────────────────────────────
        $byCourse = $db->query(
            "SELECT
                COALESCE(r.code, '—')      AS code,
                COALESCE(r.name, 'Sans cours rattaché') AS name,
                COUNT(*)::int              AS n
             $baseFrom
             LEFT JOIN resource r ON r.resource_id = s.resource_id
             GROUP BY r.code, r.name
             ORDER BY n DESC
             LIMIT 12",
            $params
        )->fetchAll();

        // ─── Volume par semaine ────────────────────────────────────
        $byWeek = $db->query(
            "SELECT
                date_trunc('week', i.sent_at)::date AS week,
                COUNT(*)::int                       AS n
             $baseFrom
             GROUP BY week
             ORDER BY week",
            $params
        )->fetchAll();

        // ─── Volume par heure de la journée ────────────────────────
        $byHour = $db->query(
            "SELECT
                EXTRACT(HOUR FROM i.sent_at)::int AS h,
                COUNT(*)::int                     AS n
             $baseFrom
             GROUP BY h
             ORDER BY h",
            $params
        )->fetchAll();

        // ─── Histogramme de longueur (input_tokens) ────────────────
        $byLen = $db->query(
            "SELECT
                CASE
                    WHEN i.input_tokens < 20    THEN '0+'
                    WHEN i.input_tokens < 50    THEN '20+'
                    WHEN i.input_tokens < 100   THEN '50+'
                    WHEN i.input_tokens < 200   THEN '100+'
                    WHEN i.input_tokens < 500   THEN '200+'
                    WHEN i.input_tokens < 1000  THEN '500+'
                    ELSE '1000+'
                END AS bucket,
                COUNT(*)::int AS n
             $baseFrom
             GROUP BY bucket
             ORDER BY MIN(i.input_tokens) NULLS FIRST",
            $params
        )->fetchAll();

        // Réordonne dans l'ordre fixe des buckets
        $order = ['0+', '20+', '50+', '100+', '200+', '500+', '1000+'];
        usort($byLen, fn($a, $b) => array_search($a['bucket'], $order) <=> array_search($b['bucket'], $order));

        // ─── Stats globales pour le header ─────────────────────────
        $globalStats = $db->query(
            "SELECT
                COUNT(DISTINCT c.user_id)::int       AS students,
                COUNT(DISTINCT s.resource_id)::int   AS courses,
                COUNT(*)::int                        AS prompts,
                COALESCE(SUM(OCTET_LENGTH(i.prompt) + OCTET_LENGTH(i.response)), 0)::bigint AS bytes
             $baseFrom",
            $params
        )->fetch();

        // ─── Sujets émergents : extraction de mots fréquents (placeholder LDA) ─
        $topWords = $this->extractTopWords($db, $baseFrom, $params, 20);

        // ─── Listes pour les filtres ───────────────────────────────
        $allCourses = $db->query(
            "SELECT resource_id, code, name FROM resource ORDER BY code"
        )->fetchAll();

        $allModels = $db->query(
            "SELECT DISTINCT name FROM model WHERE is_active = true ORDER BY name"
        )->fetchAll();

        $this->render('pages/dashboard/export', [
            'title'        => 'Corpus · Prompts',
            'filters'      => [
                'from'        => $from,
                'to'          => $to,
                'resourceIds' => $resourceIds,
                'model'       => $modelName,
                'role'        => $role,
                'min_tokens'  => $minTokens,
                'anonymised'  => $anonymised,
            ],
            'total'        => $total,
            'allCount'     => $this->countAllInteractions($db),
            'globalStats'  => $globalStats,
            'byCourse'     => $byCourse,
            'byWeek'       => $byWeek,
            'byHour'       => $byHour,
            'byLen'        => $byLen,
            'topWords'     => $topWords,
            'allCourses'   => $allCourses,
            'allModels'    => $allModels,
            'user'         => $this->currentUser(),
        ]);
    }

    /**
     * Exporte les données de recherche au format JSON (filtres = mêmes params).
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

    // ─── Helpers privés ──────────────────────────────────────────

    private function countAllInteractions(Database $db): int
    {
        return (int) $db->query('SELECT COUNT(*)::int AS n FROM interaction')->fetch()['n'];
    }

    /**
     * Extraction très simple des mots fréquents (≥ 3 lettres, hors stopwords FR/EN).
     * À remplacer par un vrai LDA pour une pertinence accrue.
     */
    private function extractTopWords(Database $db, string $baseFrom, array $params, int $limit): array
    {
        // Récupère les 5000 derniers prompts pour rester rapide
        $rows = $db->query(
            "SELECT i.prompt $baseFrom ORDER BY i.sent_at DESC LIMIT 5000",
            $params
        )->fetchAll();

        $stopwords = array_flip([
            'que','qui','est','les','des','une','dans','pour','avec','par','sur','pas','plus','tout',
            'aux','son','ses','mes','tes','nos','vos','leur','leurs','cette','ces','mais','donc','car',
            'non','oui','très','être','avoir','faire','aller','dire','peut','peux','dois','doit',
            'doivent','vais','vont','sera','sont','était','sois','soit','aussi','même','quand','comme',
            'alors','sans','aucun','quel','quelle','quels','quelles','dont','où','si','ou',
            'the','and','for','that','with','this','from','have','has','was','are','can','will',
            'should','would','could','because','what','where','when','how','why','about','some','any',
            'all','one','two','your','you','our','their','they','them',
        ]);

        $counts = [];
        foreach ($rows as $row) {
            preg_match_all('/[a-zà-ÿ]{3,}/iu', $row['prompt'], $m);
            foreach ($m[0] as $w) {
                $w = mb_strtolower($w);
                if (isset($stopwords[$w])) continue;
                $counts[$w] = ($counts[$w] ?? 0) + 1;
            }
        }
        arsort($counts);
        return array_slice($counts, 0, $limit, true);
    }
}
