<?php

namespace Tests\Unit;

use App\Models\FarmerPlantingSchedule;
use App\Services\Analytics\FarmerCropPlanService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class FarmerCropPlanServiceTest extends TestCase
{
    private function schedule(string $planting, ?string $harvest, array $extra = []): FarmerPlantingSchedule
    {
        return (new FarmerPlantingSchedule)->forceFill($extra + [
            'id' => 1, 'planting_date' => $planting, 'scheduled_harvest_date' => $harvest, 'season' => 'Wet', 'farm_area_hectares' => 2,
        ]);
    }

    private function day(string $date, ?float $rain = null, ?float $wind = null, ?float $high = 31.0): array
    {
        return ['date' => CarbonImmutable::parse($date, 'Asia/Manila'), 'rain' => $rain, 'wind' => $wind, 'high' => $high];
    }

    private function plot(FarmerPlantingSchedule $s, string $today, array $forecast = []): array
    {
        $plan = (new FarmerCropPlanService)->build(collect([$s]), $forecast, CarbonImmutable::parse($today, 'Asia/Manila'));
        $this->assertNotNull($plan);

        return $plan['plots'][0];
    }

    public function test_the_calendar_is_counted_from_the_farmers_own_dates(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20');
        $byKey = collect($plot['milestones'])->keyBy('key');

        $this->assertSame('2026-06-01', $byKey['land_prep']['start']->toDateString());      // 14 days before planting
        $this->assertSame('2026-06-14', $byKey['land_prep']['end']->toDateString());
        $this->assertSame('2026-06-15', $byKey['basal']['start']->toDateString());          // 0 to 7 days after planting
        $this->assertSame('2026-06-22', $byKey['basal']['end']->toDateString());
        $this->assertSame('2026-06-29', $byKey['topdress1']['start']->toDateString());      // 14 to 21 days
        $this->assertSame('2026-07-20', $byKey['topdress2']['start']->toDateString());      // 35 to 42 days
        $this->assertSame('2026-10-08', $byKey['harvest']['start']->toDateString());        // a week either side of the expected date
        $this->assertSame('2026-10-22', $byKey['harvest']['end']->toDateString());

        $this->assertSame(['done', 'done', 'current', 'upcoming', 'upcoming', 'upcoming'], array_column($plot['milestones'], 'state'));
        $this->assertSame('Fertilizer at planting', $plot['stage']);
    }

    public function test_steps_are_in_date_order(): void
    {
        $starts = array_map(fn ($m) => $m['start']->timestamp, $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-01')['milestones']);
        $sorted = $starts;
        sort($sorted);

        $this->assertSame($sorted, $starts);
    }

    public function test_a_short_season_has_no_second_top_dressing(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-08-01'), '2026-06-16');

        $this->assertNotContains('topdress2', array_column($plot['milestones'], 'key'));
    }

    public function test_without_a_harvest_date_there_is_no_harvest_step(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', null), '2026-06-16');

        $this->assertNotContains('harvest', array_column($plot['milestones'], 'key'));
    }

    public function test_stage_names_before_planting_between_steps_and_after_harvest(): void
    {
        $this->assertSame('Waiting for planting', $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-05-01')['stage']);
        $this->assertSame('Land preparation', $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-05')['stage']);
        $this->assertSame('Growing', $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-25')['stage']);
        $this->assertSame('Harvest', $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-10-10')['stage']);
        $this->assertSame('Past the expected harvest', $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-11-15')['stage']);
    }

    public function test_reminders_cover_what_is_due_now_and_what_starts_within_three_days(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-27');     // the planting fertilizer ended on the 22nd; the first extra round starts on the 29th
        $titles = array_column($plot['reminders'], 'title');

        $this->assertSame(['First extra fertilizer starts in 2 days'], $titles);

        $due = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20');
        $this->assertContains('Fertilizer at planting is due now', array_column($due['reminders'], 'title'));

        $quiet = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-07-10');    // nothing due, nothing within three days
        $this->assertSame([], $quiet['reminders']);
    }

    public function test_heavy_rain_holds_off_fertilizer_and_says_why(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20', [
            $this->day('2026-06-20', 2), $this->day('2026-06-21', 34), $this->day('2026-06-22', 1),
        ]);

        $this->assertCount(1, $plot['advice']);
        $this->assertSame('warning', $plot['advice'][0]['severity']);
        $this->assertStringContainsString('Hold off on the fertilizer at planting', $plot['advice'][0]['text']);
        $this->assertStringContainsString('34 mm', $plot['advice'][0]['text']);
        $this->assertStringContainsString('tomorrow', $plot['advice'][0]['text']);
    }

    public function test_no_advice_without_a_triggering_forecast_or_beyond_two_days(): void
    {
        $calm = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20', [$this->day('2026-06-20', 2), $this->day('2026-06-21', 3)]);
        $this->assertSame([], $calm['advice']);

        $late = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20', [$this->day('2026-06-23', 60)]);   // three days out
        $this->assertSame([], $late['advice']);

        $none = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20', []);                              // no forecast at all
        $this->assertSame([], $none['advice']);
    }

    public function test_heat_advice_for_fertilizer_only_when_there_is_no_rain_warning(): void
    {
        $hot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-20', [$this->day('2026-06-20', 0, null, 37)]);

        $this->assertSame('watch', $hot['advice'][0]['severity']);
        $this->assertStringContainsString('early morning or late afternoon', $hot['advice'][0]['text']);
    }

    public function test_harvest_advice_covers_rain_and_wind(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-10-10', [$this->day('2026-10-11', 30, 40)]);
        $text = implode(' ', array_column($plot['advice'], 'text'));

        $this->assertStringContainsString('harvest before it', $text);
        $this->assertStringContainsString('Strong wind', $text);
    }

    public function test_planting_advice_waits_for_the_field_to_drain(): void
    {
        $plot = $this->plot($this->schedule('2026-06-15', '2026-10-15'), '2026-06-15', [$this->day('2026-06-16', 40)]);

        // Planting is current on the 15th; a heavy-rain forecast tells the farmer to wait for the field to drain.
        $this->assertStringContainsString('once the field drains', $plot['advice'][0]['text']);
    }

    public function test_every_open_plot_gets_its_own_calendar_and_only_four_are_shown(): void
    {
        $rows = collect(range(1, 6))->map(fn ($i) => $this->schedule('2026-0'.min($i, 9).'-15', '2026-11-15', ['id' => $i]));

        $plan = (new FarmerCropPlanService)->build($rows, [], CarbonImmutable::parse('2026-06-20', 'Asia/Manila'));

        $this->assertCount(4, $plan['plots']);
        $this->assertSame(2, $plan['more']);
        $this->assertStringStartsWith('Plot 1', $plan['plots'][0]['label']);
        $this->assertStringContainsString('2.00 ha', $plan['plots'][0]['label']);
    }

    public function test_no_open_schedule_means_no_plan(): void
    {
        $this->assertNull((new FarmerCropPlanService)->build(collect(), [], CarbonImmutable::now()));
    }
}
