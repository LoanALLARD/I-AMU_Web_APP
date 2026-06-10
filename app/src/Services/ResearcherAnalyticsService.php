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

    private ResearcherAnalyticsRepository $analytics;
    private ResearcherAuthorizationRepository $auth;

    public function __construct(PDO $pdo)
    {
        $this->analytics = new ResearcherAnalyticsRepository($pdo);
        $this->auth      = new ResearcherAuthorizationRepository($pdo);
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
            return ['success' => false, 'error' => 'Aucun departement selectionne.'];
        }

        // Anti-IDOR: refuse if any requested id is not an active grant.
        $allowed = $this->authorizedDepartmentIds($researcherId);
        $scoped = array_values(array_intersect($requested, $allowed));
        if (count($scoped) !== count($requested)) {
            return ['success' => false, 'error' => 'Acces non autorise a ce perimetre.'];
        }

        $agg = $this->analytics->aggregate($scoped);
        $activity = $this->fillDays(
            $this->analytics->dailyActivity($scoped, self::ACTIVITY_DAYS),
            self::ACTIVITY_DAYS
        );

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
            ],
        ];
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
