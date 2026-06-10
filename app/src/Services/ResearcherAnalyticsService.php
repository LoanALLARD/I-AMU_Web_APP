<?php

declare(strict_types=1);

namespace Services;

use Models\ResearcherAnalyticsRepository;
use Models\ResearcherAuthorizationRepository;
use PDO;

/** Builds the researcher analysis dashboard, scoped to the researcher's own grants (anti-IDOR). */
final class ResearcherAnalyticsService
{
    private const ACTIVITY_DAYS = 30;
    private const KEYWORD_LIMIT = 30;

    /** Privacy floor: a keyword surfaces only if used by this many distinct consenting students. */
    private const KEYWORD_MIN_STUDENTS = 2;

    /**
     * Fixed salt for the export pseudonyms. Keeps the token stable across
     * exports (same student -> same token) while making it non-trivial to map
     * back to a user id from the exported file alone.
     */
    private const PSEUDONYM_SALT = 'iamu-research-export-v1';

    private ResearcherAnalyticsRepository $analytics;
    private ResearcherAuthorizationRepository $auth;

    public function __construct(
        PDO $pdo,
        ?ResearcherAnalyticsRepository $analytics = null,
        ?ResearcherAuthorizationRepository $auth = null
    ) {
        $this->analytics = $analytics ?? new ResearcherAnalyticsRepository($pdo);
        $this->auth      = $auth ?? new ResearcherAuthorizationRepository($pdo);
    }

    /**
     * Dashboard for the scope, or an error if any requested id is outside the researcher's grants.
     *
     * @param list<int> $requestedDepartmentIds
     * @return array{success:true, data:array<string,mixed>} | array{success:false, error:string}
     */
    public function dashboard(int $researcherId, array $requestedDepartmentIds): array
    {
        $requested = array_values(array_unique(array_filter(
            $requestedDepartmentIds,
            static fn (int $id): bool => $id > 0
        )));
        if ($requested === []) {
            return ['success' => false, 'error' => 'Aucun département sélectionné.'];
        }

        // Anti-IDOR: refuse if any requested id is not an active grant.
        $allowed = $this->authorizedDepartmentIds($researcherId);
        $scoped = array_values(array_intersect($requested, $allowed));
        if (count($scoped) !== count($requested)) {
            return ['success' => false, 'error' => 'Accès non autorisé à ce périmètre.'];
        }

        $agg = $this->analytics->aggregate($scoped);
        $activity = $this->fillDays(
            $this->analytics->dailyActivity($scoped, self::ACTIVITY_DAYS),
            self::ACTIVITY_DAYS
        );
        $keywords = $this->analytics->topKeywords($scoped, self::KEYWORD_LIMIT, self::KEYWORD_MIN_STUDENTS);
        $shape = $this->analytics->promptShape($scoped);

        $feedbackTotal = $agg['feedback_positive'] + $agg['feedback_negative'] + $agg['feedback_neutral'];
        $satisfaction = $feedbackTotal > 0
            ? round($agg['feedback_positive'] / $feedbackTotal * 100)
            : null;

        return [
            'success' => true,
            'data'    => [
                'volume' => [
                    'conversations' => $agg['conversations'],
                    'interactions'  => $agg['interactions'],
                    'students'      => $agg['students'],
                ],
                'usage' => [
                    'input_tokens'  => $agg['input_tokens'],
                    'output_tokens' => $agg['output_tokens'],
                    'avg_latency'   => $agg['avg_latency'] !== null ? (int) round($agg['avg_latency']) : null,
                ],
                'satisfaction' => [
                    'rate'     => $satisfaction,
                    'positive' => $agg['feedback_positive'],
                    'negative' => $agg['feedback_negative'],
                    'neutral'  => $agg['feedback_neutral'],
                ],
                'activity' => [
                    'days'   => self::ACTIVITY_DAYS,
                    'points' => $activity,
                ],
                'keywords' => [
                    'min_students' => self::KEYWORD_MIN_STUDENTS,
                    'total'        => $keywords['total'],
                    'words'        => $keywords['words'],
                ],
                'shape' => [
                    'avg_prompt_chars'    => $shape['avg_prompt_chars'] !== null ? round($shape['avg_prompt_chars']) : null,
                    'avg_prompt_words'    => $shape['avg_prompt_words'] !== null ? round($shape['avg_prompt_words'], 1) : null,
                    'avg_conversation_depth' => $shape['avg_interactions_per_conversation'] !== null
                        ? round($shape['avg_interactions_per_conversation'], 1)
                        : null,
                ],
            ],
        ];
    }

    /**
     * Research corpus for an export, scoped to the requested departments (or
     * the researcher's full perimeter when none is given). Same anti-IDOR rule
     * as the dashboard, and the rows are consent-filtered upstream. Identity is
     * pseudonymised here (export-time only, the database is untouched): no name,
     * email or student number, and the student id is replaced by a stable
     * opaque token. Each row is cast to a clean contract so the controller can
     * serialise it to JSON or CSV without touching the database shape.
     *
     * @param list<int> $requestedDepartmentIds
     * @return array{success:false, error:string}|array{success:true, scope:list<array{place:string, department:string}>, rows:list<array<string, mixed>>}
     */
    public function export(int $researcherId, array $requestedDepartmentIds): array
    {
        $allowed = $this->authorizedDepartmentIds($researcherId);
        if ($allowed === []) {
            return ['success' => false, 'error' => "Vous n'avez accès aux données d'aucun département."];
        }

        $requested = array_values(array_unique(array_filter(
            $requestedDepartmentIds,
            static fn (int $id): bool => $id > 0
        )));

        // No explicit scope => the researcher's whole accessible perimeter.
        $scope = $requested === [] ? $allowed : array_values(array_intersect($requested, $allowed));

        // Anti-IDOR: a requested id outside the active grants is refused.
        if ($requested !== [] && count($scope) !== count($requested)) {
            return ['success' => false, 'error' => 'Accès non autorisé à ce périmètre.'];
        }

        $rows = array_map(
            static fn (array $r): array => [
                'campus'                  => (string) $r['place_name'],
                'department'              => (string) $r['department_name'],
                'session_id'              => (int) $r['session_id'],
                'session_name'            => (string) $r['session_name'],
                'session_code'            => $r['session_access_code'] !== null ? (string) $r['session_access_code'] : null,
                // Pseudonym only: no name, email or student number leaves the
                // platform. Stable per student, so a researcher can still group
                // a student's interactions without being able to re-identify.
                'student'                 => self::pseudonym((int) $r['student_id']),
                'conversation_id'         => (int) $r['conversation_id'],
                'conversation_name'       => (string) $r['conversation_name'],
                'conversation_created_at' => $r['conversation_created'] !== null ? (string) $r['conversation_created'] : null,
                'is_archived'             => (bool) $r['is_archived'],
                'interaction_id'          => (int) $r['interaction_id'],
                'prompt'                  => (string) $r['prompt'],
                'response'                => (string) $r['response'],
                'model'                   => $r['model_name'] !== null ? (string) $r['model_name'] : null,
                'input_tokens'            => $r['input_tokens']  !== null ? (int) $r['input_tokens']  : null,
                'output_tokens'           => $r['output_tokens'] !== null ? (int) $r['output_tokens'] : null,
                'latency_ms'              => $r['latency']       !== null ? (int) $r['latency']       : null,
                'user_feedback'           => $r['user_feedback'] !== null ? (int) $r['user_feedback'] : null,
                'sent_at'                 => $r['sent_at'] !== null ? (string) $r['sent_at'] : null,
            ],
            $this->analytics->exportRows($scope)
        );

        return [
            'success' => true,
            'scope'   => $this->scopeLabels($researcherId, $scope),
            'rows'    => $rows,
        ];
    }

    /**
     * Stable, non-identifying token for a student in an export. Derived from
     * the user id with a fixed salt, so the same student always maps to the
     * same token across exports, but the file alone does not reveal the id.
     */
    private static function pseudonym(int $studentId): string
    {
        return 'etudiant-' . substr(hash('sha256', self::PSEUDONYM_SALT . ':' . $studentId), 0, 10);
    }

    /**
     * Campus/department labels for the exported scope, drawn from the
     * researcher's active grants so only authorised names are surfaced.
     *
     * @param list<int> $departmentIds
     * @return list<array{place:string, department:string}>
     */
    private function scopeLabels(int $researcherId, array $departmentIds): array
    {
        $wanted = array_fill_keys($departmentIds, true);
        $labels = [];
        foreach ($this->auth->findActiveByResearcher($researcherId) as $row) {
            if (isset($wanted[(int) $row['department_id']])) {
                $labels[] = [
                    'place'      => (string) $row['place_name'],
                    'department' => (string) $row['department_name'],
                ];
            }
        }

        return $labels;
    }

    /**
     * The department ids the researcher currently has an active grant on.
     *
     * @return list<int>
     */
    private function authorizedDepartmentIds(int $researcherId): array
    {
        return array_map(
            static fn (array $row): int => (int) $row['department_id'],
            $this->auth->findActiveByResearcher($researcherId)
        );
    }

    /**
     * Zero-fills missing days into a continuous last-$days series.
     *
     * @param list<array{day:string, total:int}> $rows
     * @return list<array{day:string, total:int}>
     */
    private function fillDays(array $rows, int $days): array
    {
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['day']] = $row['total'];
        }

        $series = [];
        $cursor = new \DateTimeImmutable('today');
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $cursor->sub(new \DateInterval('P' . $i . 'D'))->format('Y-m-d');
            $series[] = ['day' => $day, 'total' => $byDay[$day] ?? 0];
        }

        return $series;
    }
}
