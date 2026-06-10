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
    private EnergyEstimator $energy;

    public function __construct(PDO $pdo)
    {
        $this->stats = new UserStatsRepository($pdo);
        $this->energy = new EnergyEstimator();
    }

    /**
     * Personal consumption summary for the given user.
     *
     * @return array{
     *     conversations:int, interactions:int,
     *     input_tokens:int, output_tokens:int,
     *     messages_per_conversation:?float, top_model:?string, last_activity:?string,
     *     energy:array{wh:float, gco2:float, eq_car_km:float, eq_phone_charges:float, eq_led_hours:float},
     *     energy_factors:array{wh_per_output_token:float, input_token_weight:float, reference_size_b:float, grid_gco2_per_kwh:float},
     *     activity_days:int, activity:list<array{day:string, total:int}>, activity_total:int
     * }
     */
    public function personal(int $userId): array
    {
        $agg = $this->stats->personalAggregate($userId);
        $topModel = $this->stats->topModel($userId);
        $energy = $this->energy->estimate($this->stats->tokensByModel($userId));
        $activity = $this->fillDays(
            $this->stats->dailyActivity($userId, self::ACTIVITY_DAYS),
            self::ACTIVITY_DAYS
        );
        $activityTotal = array_sum(array_column($activity, 'total'));

        return [
            'conversations'             => $agg['conversations'],
            'interactions'              => $agg['interactions'],
            'input_tokens'              => $agg['input_tokens'],
            'output_tokens'             => $agg['output_tokens'],
            'messages_per_conversation' => $agg['conversations'] > 0
                ? round($agg['interactions'] / $agg['conversations'], 1)
                : null,
            'top_model'                 => $topModel['name'] ?? null,
            'last_activity'             => $agg['last_activity'],
            'energy'                    => $energy,
            'energy_factors'            => $this->energy->factors(),
            'activity_days'             => self::ACTIVITY_DAYS,
            'activity'                  => $activity,
            'activity_total'            => $activityTotal,
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
