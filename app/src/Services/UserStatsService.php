<?php

declare(strict_types=1);

namespace Services;

use Models\UserStatsRepository;
use PDO;

/** Builds the personal usage stats shown on a user's own profile page. */
final class UserStatsService
{
    private const ACTIVITY_DAYS = 30;

    private UserStatsRepository $stats;

    public function __construct(PDO $pdo)
    {
        $this->stats = new UserStatsRepository($pdo);
    }

    /**
     * Personal consumption summary for the given user.
     *
     * @return array{
     *     conversations:int, interactions:int,
     *     input_tokens:int, output_tokens:int, avg_latency:?int,
     *     activity_days:int, activity:list<array{day:string, total:int}>, activity_total:int
     * }
     */
    public function personal(int $userId): array
    {
        $agg = $this->stats->personalAggregate($userId);
        $activity = $this->fillDays(
            $this->stats->dailyActivity($userId, self::ACTIVITY_DAYS),
            self::ACTIVITY_DAYS
        );
        $activityTotal = array_sum(array_column($activity, 'total'));

        return [
            'conversations'  => $agg['conversations'],
            'interactions'   => $agg['interactions'],
            'input_tokens'   => $agg['input_tokens'],
            'output_tokens'  => $agg['output_tokens'],
            'avg_latency'    => $agg['avg_latency'] !== null ? (int) round($agg['avg_latency']) : null,
            'activity_days'  => self::ACTIVITY_DAYS,
            'activity'       => $activity,
            'activity_total' => $activityTotal,
        ];
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
