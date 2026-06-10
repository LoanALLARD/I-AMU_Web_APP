<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Services\EnergyEstimator;

/**
 * Unit tests for EnergyEstimator. The factors are fixed constants, so the
 * arithmetic is fully deterministic: output tokens cost more than input, the
 * cost scales linearly with model size, an unparseable size falls back to the
 * reference, and the CO2 / equivalences are derived from the energy. Pure logic.
 */
final class EnergyEstimatorTest extends TestCase
{
    private EnergyEstimator $estimator;

    protected function setUp(): void
    {
        $this->estimator = new EnergyEstimator();
    }

    /**
     * @param array{model_size:?string, input_tokens:int, output_tokens:int} ...$rows
     * @return list<array{model_size:?string, input_tokens:int, output_tokens:int}>
     */
    private static function rows(array ...$rows): array
    {
        return array_values($rows);
    }

    public function testEmptyInputIsAllZeros(): void
    {
        $r = $this->estimator->estimate([]);

        self::assertSame(0.0, $r['wh']);
        self::assertSame(0.0, $r['gco2']);
        self::assertSame(0.0, $r['eq_car_km']);
        self::assertSame(0.0, $r['eq_phone_charges']);
        self::assertSame(0.0, $r['eq_led_hours']);
    }

    public function testOutputTokensDriveTheEstimate(): void
    {
        // 100000 output tokens at the reference 7B size: 1e5 * 0.0003 * 1 = 30 Wh.
        $r = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(30.0, $r['wh'], 0.001);
    }

    public function testInputTokensAreWeightedLessThanOutput(): void
    {
        $inputOnly = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 100000, 'output_tokens' => 0]
        ));
        $outputOnly = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        // Input weight is 0.2: 1e5 * 0.2 * 0.0003 = 6 Wh, vs 30 Wh for output.
        self::assertEqualsWithDelta(6.0, $inputOnly['wh'], 0.001);
        self::assertLessThan($outputOnly['wh'], $inputOnly['wh']);
    }

    public function testInputAndOutputAreCombined(): void
    {
        // weighted = 100000 + 100000*0.2 = 120000 -> 120000 * 0.0003 = 36 Wh.
        $r = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 100000, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(36.0, $r['wh'], 0.001);
    }

    public function testLargerModelCostsProportionallyMore(): void
    {
        // 70B is 10x the 7B reference -> 10x the energy for the same tokens.
        $small = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));
        $large = $this->estimator->estimate(self::rows(
            ['model_size' => '70b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(300.0, $large['wh'], 0.001);
        self::assertEqualsWithDelta(10.0, $large['wh'] / $small['wh'], 0.001);
    }

    public function testNullSizeFallsBackToReference(): void
    {
        $nullSize = $this->estimator->estimate(self::rows(
            ['model_size' => null, 'input_tokens' => 0, 'output_tokens' => 100000]
        ));
        $reference = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta($reference['wh'], $nullSize['wh'], 0.001);
    }

    public function testUnparseableSizeFallsBackToReference(): void
    {
        $garbage = $this->estimator->estimate(self::rows(
            ['model_size' => 'mistral', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(30.0, $garbage['wh'], 0.001);
    }

    public function testDecimalSizeIsParsed(): void
    {
        // 3.5B is half the 7B reference -> half the energy.
        $r = $this->estimator->estimate(self::rows(
            ['model_size' => '3.5b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(15.0, $r['wh'], 0.001);
    }

    public function testMultipleModelsAreSummed(): void
    {
        $r = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000],
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(60.0, $r['wh'], 0.001);
    }

    public function testCarbonAndEquivalencesAreDerivedFromEnergy(): void
    {
        // 30 Wh -> gCO2 = 30/1000*50 = 1.5; car = 1.5/120; phone = 30/12; led = 30/8.
        $r = $this->estimator->estimate(self::rows(
            ['model_size' => '7b', 'input_tokens' => 0, 'output_tokens' => 100000]
        ));

        self::assertEqualsWithDelta(1.5, $r['gco2'], 0.001);
        self::assertEqualsWithDelta(0.01, $r['eq_car_km'], 0.001);     // round(0.0125, 2)
        self::assertEqualsWithDelta(2.5, $r['eq_phone_charges'], 0.001);
        self::assertEqualsWithDelta(3.8, $r['eq_led_hours'], 0.001);   // round(3.75, 1)
    }

    public function testFactorsExposeTheConstants(): void
    {
        $f = $this->estimator->factors();

        self::assertSame(0.0003, $f['wh_per_output_token']);
        self::assertSame(0.2, $f['input_token_weight']);
        self::assertSame(7.0, $f['reference_size_b']);
        self::assertSame(50.0, $f['grid_gco2_per_kwh']);
    }
}
