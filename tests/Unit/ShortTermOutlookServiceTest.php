<?php

namespace Tests\Unit;

use App\Services\Prediction\ShortTermOutlookService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ShortTermOutlookServiceTest extends TestCase
{
    public function test_builds_four_weeks_covering_the_full_target_month(): void
    {
        $service = new ShortTermOutlookService;
        $targetMonth = CarbonImmutable::parse('2026-11-01');

        $outlook = $service->build([
            'previous_rainfall' => 100.0,
            'rainfall' => 100.0,
            'previous_temp' => 27.0,
            'temp_avg' => 27.0,
        ], $targetMonth);

        $this->assertCount(4, $outlook['weeks']);
        $this->assertSame(1, $outlook['weeks'][0]['week']);
        $this->assertSame(4, $outlook['weeks'][3]['week']);

        // The four weeks must cover every day of the month exactly once,
        // in order, with no gaps or overlaps.
        $this->assertSame('Nov 1 - 8', $outlook['weeks'][0]['date_range_label']);
        $this->assertSame('Nov 9 - 16', $outlook['weeks'][1]['date_range_label']);
        $this->assertSame('Nov 17 - 23', $outlook['weeks'][2]['date_range_label']);
        $this->assertSame('Nov 24 - 30', $outlook['weeks'][3]['date_range_label']);
    }

    public function test_interpolates_monotonically_between_previous_and_target_values(): void
    {
        $service = new ShortTermOutlookService;
        $targetMonth = CarbonImmutable::parse('2026-06-01');

        $outlook = $service->build([
            'previous_rainfall' => 100.0,
            'rainfall' => 300.0,
            'previous_temp' => 25.0,
            'temp_avg' => 25.0,
        ], $targetMonth);

        $rainfallValues = array_column($outlook['weeks'], 'rainfall');

        // Increasing rainfall trend: each week's interpolated value must be
        // strictly greater than the one before it, and all four must fall
        // strictly between the two real anchor points (100 and 300).
        foreach ($rainfallValues as $index => $value) {
            $this->assertGreaterThan(100.0, $value);
            $this->assertLessThan(300.0, $value);

            if ($index > 0) {
                $this->assertGreaterThan($rainfallValues[$index - 1], $value);
            }
        }
    }

    public function test_flat_input_produces_stable_trend_and_constant_weeks(): void
    {
        $service = new ShortTermOutlookService;
        $targetMonth = CarbonImmutable::parse('2026-03-01');

        $outlook = $service->build([
            'previous_rainfall' => 180.0,
            'rainfall' => 180.0,
            'previous_temp' => 28.0,
            'temp_avg' => 28.0,
        ], $targetMonth);

        $this->assertSame('Stable', $outlook['rainfall_trend']['direction']);
        $this->assertSame('Stable', $outlook['temperature_trend']['direction']);
        $this->assertSame(0.0, $outlook['rainfall_trend']['delta']);

        foreach ($outlook['weeks'] as $week) {
            $this->assertSame(180.0, $week['rainfall']);
            $this->assertSame(28.0, $week['temperature']);
        }
    }

    public function test_planning_tip_prioritizes_rainfall_trend_over_temperature_trend(): void
    {
        $service = new ShortTermOutlookService;
        $targetMonth = CarbonImmutable::parse('2026-09-01');

        // Both rainfall and temperature are rising — rainfall is the
        // strongest single yield driver (see FeatureCatalog.php), so the
        // tip must lead with rainfall, not temperature.
        $outlook = $service->build([
            'previous_rainfall' => 100.0,
            'rainfall' => 250.0,
            'previous_temp' => 26.0,
            'temp_avg' => 29.0,
        ], $targetMonth);

        $this->assertStringContainsStringIgnoringCase('wetter', $outlook['planning_tip']);
    }

    public function test_disclaimer_is_always_present_and_labels_this_as_an_approximation(): void
    {
        $service = new ShortTermOutlookService;

        $outlook = $service->build([
            'previous_rainfall' => 150.0,
            'rainfall' => 150.0,
            'previous_temp' => 27.0,
            'temp_avg' => 27.0,
        ], CarbonImmutable::parse('2026-02-01'));

        $this->assertStringContainsString('not an independent weekly prediction', $outlook['disclaimer']);
    }

    public function test_missing_previous_values_produce_no_outlook_instead_of_copying_the_current_value(): void
    {
        $service = new ShortTermOutlookService;

        $outlook = $service->build([
            'rainfall' => 200.0,
            'temp_avg' => 30.0,
        ], CarbonImmutable::parse('2026-07-01'));

        // A missing "last month" value is never replaced by this month's value: no outlook at all.
        $this->assertNull($outlook);
    }
}
