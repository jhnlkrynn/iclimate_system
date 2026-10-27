<?php

namespace Tests\Unit;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Support\VarietyHarvestEstimator;
use Tests\TestCase;

class VarietyHarvestEstimatorTest extends TestCase
{
    private function estimator(): VarietyHarvestEstimator
    {
        return new VarietyHarvestEstimator;
    }

    /** Only the given maturity cells are filled; every other figure is empty. */
    private function variety(array $maturity): RiceVariety
    {
        $blank = [];
        foreach ([...RiceVariety::COMBOS, 'seed_production'] as $combo) {
            foreach (['', '_min', '_max'] as $part) {
                $blank['maturity_'.$combo.$part] = null;
            }
        }

        return RiceVariety::factory()->make(array_merge($blank, $maturity));
    }

    public function test_the_exact_season_and_method_cell_is_used_with_its_range_as_the_window(): void
    {
        // NSIC 2026 Rc 824H: TPR WS "108 (103-113)".
        $variety = $this->variety(['maturity_tpr_ws' => 108, 'maturity_tpr_ws_min' => 103, 'maturity_tpr_ws_max' => 113]);

        $result = $this->estimator()->estimate($variety, '2026-07-01', 'Wet', 'Transplanted', 'sowing');

        $this->assertSame('2026-10-17', $result['expected_harvest_date']->toDateString());   // + 108 days
        $this->assertSame('2026-10-12', $result['window_start']->toDateString());            // + 103
        $this->assertSame('2026-10-22', $result['window_end']->toDateString());              // + 113
        $this->assertSame(108, $result['maturity_days_used']);
        $this->assertSame('trial', $result['basis_code']);
        $this->assertSame('tpr_ws', $result['combo']);
        $this->assertSame('Based on Wet-season transplanted trials.', $result['basis']);
    }

    public function test_dry_and_wet_season_read_their_own_columns(): void
    {
        $variety = $this->variety(['maturity_tpr_ds' => 115, 'maturity_tpr_ws' => 109]);

        $dry = $this->estimator()->estimate($variety, '2026-11-15', 'Dry', 'Transplanted');
        $wet = $this->estimator()->estimate($variety, '2026-11-15', 'Wet', 'Transplanted');

        $this->assertSame([115, 'tpr_ds'], [$dry['maturity_days_used'], $dry['combo']]);
        $this->assertSame([109, 'tpr_ws'], [$wet['maturity_days_used'], $wet['combo']]);
        $this->assertSame(6, (int) $wet['expected_harvest_date']->diffInDays($dry['expected_harvest_date']));
    }

    public function test_an_empty_cell_falls_back_to_the_same_method_in_the_other_season(): void
    {
        // NSIC 2016 Rc 480 has only TPR WS (107).
        $result = $this->estimator()->estimate($this->variety(['maturity_tpr_ws' => 107]), '2026-12-01', 'Dry', 'Transplanted');

        $this->assertSame(['other_season', 'tpr_ws', 107], [$result['basis_code'], $result['combo'], $result['maturity_days_used']]);
        $this->assertSame('No Dry-season transplanted trials; based on Wet-season transplanted trials.', $result['basis']);
    }

    public function test_then_to_the_other_method_in_the_same_season(): void
    {
        $variety = $this->variety(['maturity_dwsr_ws' => 104, 'maturity_dwsr_ds' => 99]);

        $result = $this->estimator()->estimate($variety, '2026-06-01', 'Wet', 'Transplanted');

        $this->assertSame(['other_method', 'dwsr_ws', 104], [$result['basis_code'], $result['combo'], $result['maturity_days_used']]);
    }

    public function test_then_to_the_seed_production_maturity_and_its_range(): void
    {
        $variety = $this->variety(['maturity_seed_production' => 117, 'maturity_seed_production_min' => 115, 'maturity_seed_production_max' => 118]);

        $result = $this->estimator()->estimate($variety, '2026-06-01', 'Wet', 'Direct Wet Seeded');

        $this->assertSame(['seed_production', 117], [$result['basis_code'], $result['maturity_days_used']]);
        $this->assertSame('2026-09-26', $result['expected_harvest_date']->toDateString());
        $this->assertSame(['2026-09-24', '2026-09-27'], [$result['window_start']->toDateString(), $result['window_end']->toDateString()]);
    }

    public function test_a_variety_without_maturity_data_uses_the_115_day_default_and_says_so(): void
    {
        $result = $this->estimator()->estimate($this->variety([]), '2026-06-01', 'Wet', 'Transplanted');

        $this->assertSame(['default', 115], [$result['basis_code'], $result['maturity_days_used']]);
        $this->assertSame('2026-09-24', $result['expected_harvest_date']->toDateString());
        // No range: five days either side.
        $this->assertSame(['2026-09-19', '2026-09-29'], [$result['window_start']->toDateString(), $result['window_end']->toDateString()]);
        $this->assertSame('No NSIC maturity data; using the 115-day default.', $result['basis']);
    }

    public function test_no_variety_at_all_is_the_115_day_default(): void
    {
        $result = $this->estimator()->estimate(null, '2026-06-01', 'Wet');

        $this->assertSame('2026-09-24', $result['expected_harvest_date']->toDateString());
        $this->assertSame('No variety chosen; using the 115-day default.', $result['basis']);
    }

    public function test_a_transplanting_date_takes_off_the_seedling_age(): void
    {
        $variety = $this->variety(['maturity_tpr_ws' => 108, 'maturity_tpr_ws_min' => 103, 'maturity_tpr_ws_max' => 113]);

        $sown = $this->estimator()->estimate($variety, '2026-07-01', 'Wet', 'Transplanted', 'sowing', 21);
        $transplanted = $this->estimator()->estimate($variety, '2026-07-01', 'Wet', 'Transplanted', 'transplanting', 21);
        $olderSeedlings = $this->estimator()->estimate($variety, '2026-07-01', 'Wet', 'Transplanted', 'transplanting', 25);

        $this->assertSame('2026-10-17', $sown['expected_harvest_date']->toDateString());           // + 108
        $this->assertSame('2026-09-26', $transplanted['expected_harvest_date']->toDateString());   // + (108 − 21)
        $this->assertSame(['2026-09-21', '2026-10-01'], [$transplanted['window_start']->toDateString(), $transplanted['window_end']->toDateString()]);
        $this->assertSame('2026-09-22', $olderSeedlings['expected_harvest_date']->toDateString()); // + (108 − 25)
        $this->assertSame(108, $transplanted['maturity_days_used']);
    }

    public function test_direct_seeding_counts_from_the_date_itself(): void
    {
        $variety = $this->variety(['maturity_dwsr_ws' => 104]);

        // "Transplanting date" makes no sense for direct seeding: no seedling age is taken off.
        $result = $this->estimator()->estimate($variety, '2026-07-01', 'Wet', 'Direct Dry Seeded', 'transplanting', 21);

        $this->assertSame(['dwsr_ws', '2026-10-13'], [$result['combo'], $result['expected_harvest_date']->toDateString()]);
    }

    public function test_an_unknown_establishment_assumes_transplanting_and_says_so(): void
    {
        $result = $this->estimator()->estimate($this->variety(['maturity_tpr_ws' => 107]), '2026-07-01', 'Wet');

        $this->assertSame('tpr_ws', $result['combo']);
        $this->assertStringEndsWith('(Transplanting assumed.)', $result['basis']);
    }

    public function test_a_planting_schedule_brings_its_own_variety_date_season_and_method(): void
    {
        $variety = $this->variety(['maturity_tpr_ws' => 107]);
        $schedule = (new FarmerPlantingSchedule)->forceFill([
            'rice_variety_id' => 7, 'planting_date' => '2026-07-10', 'season' => 'Wet',
            'crop_establishment' => 'Transplanted', 'date_basis' => 'transplanting', 'seedling_age_days' => 20,
        ])->setRelation('riceVariety', $variety);

        $result = $this->estimator()->estimate($schedule);

        $this->assertSame('2026-10-05', $result['expected_harvest_date']->toDateString());   // + (107 − 20) = 87 days
        $this->assertNull($this->estimator()->estimate((new FarmerPlantingSchedule)->forceFill(['season' => 'Wet'])));
    }

    public function test_the_season_comes_from_the_month_when_it_is_not_given(): void
    {
        $variety = $this->variety(['maturity_tpr_ds' => 115, 'maturity_tpr_ws' => 109]);

        $this->assertSame('tpr_ds', $this->estimator()->estimate($variety, '2026-12-01', null, 'Transplanted')['combo']);
        $this->assertSame('tpr_ws', $this->estimator()->estimate($variety, '2026-06-01', null, 'Transplanted')['combo']);
    }
}
