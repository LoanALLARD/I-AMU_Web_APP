<?php

declare(strict_types=1);

namespace Services;

/**
 * Rough environmental footprint of LLM usage, estimated from token counts.
 *
 * This is an INDICATION, not a measurement: real energy use depends on the
 * GPU, datacenter PUE, batching and the carbon intensity of the grid, none of
 * which we record. The figures below are deliberate, sourced order-of-magnitude
 * factors so the estimate stays defensible and a correction is a one-line edit.
 * The UI must always present the result as an estimate.
 *
 * Method: energy ~= weighted_tokens * Wh-per-token(model size); the result is
 * then converted to CO2 via the grid carbon intensity, and to relatable
 * equivalents. Output tokens are weighted more than input tokens because
 * autoregressive generation runs the model once per produced token.
 */
final class EnergyEstimator
{
    /**
     * Energy per output token, in watt-hours, for a mid-size (~7-8B) local model.
     * Order of magnitude from public LLM-inference energy studies (~0.05-0.5 Wh
     * per request of a few hundred tokens). Conservative low end for a small model.
     */
    private const WH_PER_OUTPUT_TOKEN_BASE = 0.0003;

    /** Input tokens are only read, not generated: counted at a fraction of output cost. */
    private const INPUT_TOKEN_WEIGHT = 0.2;

    /** Reference model size (billions of params) the base factor is calibrated for. */
    private const REFERENCE_SIZE_B = 7.0;

    /**
     * Carbon intensity of the electricity grid, gCO2 per kWh.
     * ~50 g/kWh is the order of magnitude for the French mix (mostly nuclear),
     * where AMU infrastructure most likely runs. Override for another grid.
     */
    private const GRID_GCO2_PER_KWH = 50.0;

    // Equivalences (per unit), public order-of-magnitude figures.
    private const GCO2_PER_KM_CAR        = 120.0; // avg car, gCO2/km
    private const WH_PER_PHONE_CHARGE    = 12.0;  // full smartphone charge
    private const WH_PER_LED_HOUR        = 8.0;   // a 8W LED bulb for one hour

    /**
     * Estimate the footprint from token totals grouped by model size.
     *
     * @param list<array{model_size:?string, input_tokens:int, output_tokens:int}> $byModel
     * @return array{
     *     wh:float, gco2:float,
     *     eq_car_km:float, eq_phone_charges:float, eq_led_hours:float
     * }
     */
    public function estimate(array $byModel): array
    {
        $wh = 0.0;
        foreach ($byModel as $row) {
            $sizeFactor = $this->sizeFactor($row['model_size']);
            $weightedTokens = $row['output_tokens']
                + $row['input_tokens'] * self::INPUT_TOKEN_WEIGHT;
            $wh += $weightedTokens * self::WH_PER_OUTPUT_TOKEN_BASE * $sizeFactor;
        }

        $gco2 = $wh / 1000.0 * self::GRID_GCO2_PER_KWH;

        return [
            'wh'               => round($wh, 2),
            'gco2'             => round($gco2, 2),
            'eq_car_km'        => round($gco2 / self::GCO2_PER_KM_CAR, 2),
            'eq_phone_charges' => round($wh / self::WH_PER_PHONE_CHARGE, 1),
            'eq_led_hours'     => round($wh / self::WH_PER_LED_HOUR, 1),
        ];
    }

    /**
     * The constants behind the estimate, so the UI can show the actual formula
     * without hardcoding (and drifting from) these values.
     *
     * @return array{
     *     wh_per_output_token:float, input_token_weight:float,
     *     reference_size_b:float, grid_gco2_per_kwh:float
     * }
     */
    public function factors(): array
    {
        return [
            'wh_per_output_token' => self::WH_PER_OUTPUT_TOKEN_BASE,
            'input_token_weight'  => self::INPUT_TOKEN_WEIGHT,
            'reference_size_b'    => self::REFERENCE_SIZE_B,
            'grid_gco2_per_kwh'   => self::GRID_GCO2_PER_KWH,
        ];
    }

    /**
     * Scales the per-token cost by model size relative to the reference.
     * Energy scales roughly with parameter count, so we use a linear ratio.
     * Falls back to the reference size when `size` is missing or unparseable.
     */
    private function sizeFactor(?string $size): float
    {
        $billions = $this->parseSizeB($size);
        return $billions / self::REFERENCE_SIZE_B;
    }

    /** Parses a free-text size like "7b", "8B", "70b" into billions of params. */
    private function parseSizeB(?string $size): float
    {
        if ($size === null || !preg_match('/([0-9]+(?:\.[0-9]+)?)/', $size, $m)) {
            return self::REFERENCE_SIZE_B;
        }
        $value = (float) $m[1];
        return $value > 0 ? $value : self::REFERENCE_SIZE_B;
    }
}
