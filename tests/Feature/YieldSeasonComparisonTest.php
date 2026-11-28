<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Analytics\YieldSeasonComparison;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** /analysis/yield "Same season, past years" (2026-11-28). Today is fixed at 2026-11-15: Dry 2026–27 is this season. */
class YieldSeasonComparisonTest extends TestCase
{
    use RefreshDatabase;

    private string $archive;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-15 09:00:00');
        Http::preventStrayRequests();   // the page never calls an outside service
        config(['prediction.rice_yield.gate' => 'label']);
        $this->archive = sys_get_temp_dir().'/yc-archive-'.uniqid().'.json';
        file_put_contents($this->archive, json_encode(['fetched_at' => '2026-11-10T01:10:00+08:00', 'months' => []]));
        config(['services.open_meteo.archive_file' => $this->archive]);
    }

    protected function tearDown(): void
    {
        @unlink($this->archive);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function farm(User $farmer, string $status = Farm::VERIFICATION_APPROVED): Farm
    {
        $farm = Farm::query()->create([
            'user_id' => $farmer->id, 'farm_name' => 'Bukid 1', 'barangay' => 'Humayingan', 'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2, 'farm_size_method' => Farm::SIZE_EXACT, 'is_primary' => true,
        ]);
        $farm->forceFill(['verification_status' => $status])->save();

        return $farm->refresh();
    }

    private function harvest(User $farmer, Farm $farm, string $harvestedOn, float $yield, array $more = []): FarmerPlantingSchedule
    {
        return FarmerPlantingSchedule::query()->create($more + [
            'user_id' => $farmer->id, 'farm_id' => $farm->id, 'planting_date' => Carbon::parse($harvestedOn)->subMonths(4)->toDateString(),
            'scheduled_harvest_date' => $harvestedOn, 'harvested_on' => $harvestedOn, 'season' => 'Dry', 'farm_type' => 'Irrigated',
            'barangay' => 'Humayingan', 'farm_area_hectares' => 2, 'actual_yield' => $yield,
        ]);
    }

    private function rice(string $barangay, int $year, float $yield, string $remarks = 'Barangay harvest report', string $type = 'Irrigated'): RiceProduction
    {
        return RiceProduction::query()->create(['barangay' => $barangay, 'season' => 'Dry', 'irrigation_type' => $type,
            'yield_per_hectare' => $yield, 'area_hectares' => 10, 'total_production' => round($yield * 10, 2), 'year' => $year, 'remarks' => $remarks]);
    }

    private function estimate(User $farmer, Farm $farm, float $value): YieldPredictionRequest
    {
        return YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id, 'farm_id' => $farm->id, 'scope' => YieldPredictionRequest::SCOPE_FARM, 'barangay' => 'Humayingan',
            'season' => 'Dry', 'farm_type' => 'Irrigated', 'area_hectares' => 2, 'target_month' => '2027-03-01',
            'status' => YieldPredictionRequest::DONE, 'predicted_yield' => $value, 'total_tons' => $value * 2, 'completed_at' => now(),
        ]);
    }

    /** @return array<int, array<string, mixed>> past rows keyed by the year the season starts */
    private function rows(array $comparison): array
    {
        return collect($comparison['rows'])->keyBy('start_year')->all();
    }

    public function test_farm_history_is_used_first_and_demo_harvests_are_left_out(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer);
        $this->harvest($farmer, $farm, '2025-03-10', 4.5);                                        // Dry 2024–25
        $this->harvest($farmer, $farm, '2024-03-12', 9.9, ['notes' => '[demo_seed] seeded']);    // Dry 2023–24, demo
        $this->rice('Humayingan', 2024, 3.1);
        $this->rice('Humayingan', 2023, 3.7);
        $this->estimate($farmer, $farm, 3.2);

        $c = app(YieldSeasonComparison::class)->build($farmer, $farm, 'Dry');
        $rows = $this->rows($c);

        $this->assertSame([2021, 2022, 2023, 2024, 2025], array_keys($rows));
        $this->assertSame(2026, $c['current']['start_year']);
        $this->assertSame(['t_ha' => 4.5, 'tons' => 9.0, 'source' => 'farm'], array_intersect_key($rows[2024]['yield'], array_flip(['t_ha', 'tons', 'source'])));
        $this->assertSame('barangay', $rows[2023]['yield']['source']);                              // the demo harvest is not the farm's record
        $this->assertSame(3.7, $rows[2023]['yield']['t_ha']);
        $this->assertSame(3.2, $c['estimate']['t_ha']);
        $this->assertFalse($c['estimate']['actual']);
        $this->assertSame(4.1, $c['usual']);                                                         // (4.5 + 3.7) / 2
        $this->assertEquals(-22, $c['diff_pct']);

        $html = $this->actingAs($farmer)->get('/analysis/yield?season=Dry')->assertOk()->getContent();
        $this->assertStringContainsString('Same season, past years', $html);
        $this->assertStringContainsString('Estimate 3.20 t/ha vs usual 4.1 t/ha (avg last 2 dry seasons)', $html);
        $this->assertStringContainsString('22% lower', $html);
        $this->assertStringContainsString('Your harvests (1)', $html);
        $this->assertStringContainsString('data-cmp-bar="estimate"', $html);
        $this->assertStringContainsString('data-cmp-row="current"', $html);
        $this->assertStringContainsString('Ambulong station', $html);
    }

    public function test_barangay_records_fill_a_season_without_farm_harvests(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer);
        $this->rice('Humayingan', 2023, 4.0);
        $this->rice('Humayingan', 2023, 2.0, 'Barangay harvest report', 'Rainfed');   // another farm type
        $this->rice('Prenza', 2023, 5.5);                                              // another barangay

        $rows = $this->rows(app(YieldSeasonComparison::class)->build($farmer, $farm, 'Dry'));

        $this->assertSame(['t_ha' => 4.0, 'source' => 'barangay', 'count' => 1], array_intersect_key($rows[2023]['yield'], array_flip(['t_ha', 'source', 'count'])));
        $this->actingAs($farmer)->get('/analysis/yield?season=Dry')->assertOk()->assertSee('Barangay records');
    }

    public function test_lian_wide_records_are_the_last_fallback_without_demo_rows_or_an_unapproved_farm(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer, Farm::VERIFICATION_PENDING);
        $this->harvest($farmer, $farm, '2023-03-10', 5.0);                       // farm not approved: not used
        $this->rice('Prenza', 2022, 3.4);
        $this->rice('Binubusan', 2022, 3.8);
        $this->rice('Humayingan', 2022, 9.0, '[demo_seed] Demo mixed');           // demo row: never used

        $c = app(YieldSeasonComparison::class)->build($farmer, $farm, 'Dry');
        $rows = $this->rows($c);

        $this->assertFalse($c['farm_approved']);
        $this->assertSame(['t_ha' => 3.6, 'source' => 'lian', 'count' => 2], array_intersect_key($rows[2022]['yield'], array_flip(['t_ha', 'source', 'count'])));
        $this->actingAs($farmer)->get('/analysis/yield?season=Dry')->assertOk()
            ->assertSee('Lian-wide records (2 barangays)')
            ->assertSee('Your farm is not approved yet');
    }

    public function test_missing_years_show_no_record(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer);
        $this->rice('Humayingan', 2024, 3.9);

        $c = app(YieldSeasonComparison::class)->build($farmer, $farm, 'Dry');
        $rows = $this->rows($c);
        $this->assertNull($rows[2021]['yield']);
        $this->assertNull($rows[2025]['yield']);
        $this->assertNull($c['estimate']);
        // Jan–Mar 2026 are past the station file and not in the (empty) archive: only part of that season's weather.
        $this->assertFalse($rows[2025]['weather']['complete']);
        $this->assertTrue($rows[2021]['weather']['complete']);

        $html = $this->actingAs($farmer)->get('/analysis/yield?season=Dry')->assertOk()->getContent();
        $table = substr($html, strpos($html, 'data-cmp-table'));
        $table = substr($table, 0, strpos($table, '</table>'));
        $this->assertSame(5, substr_count($table, 'No record'));   // four past yields + this season's rain (no forecast stored)
        $this->assertStringContainsString('No estimate for this season yet.', $html);
        $this->assertStringContainsString('2 of 5 months', $html);
    }

    public function test_insight_compares_the_forecast_season_rain_with_stored_seasons(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer);
        foreach ([[2026, 11], [2026, 12], [2027, 1], [2027, 2], [2027, 3]] as [$y, $m]) {
            DB::table('monthly_weather_predictions')->insert(['prediction_year' => $y, 'prediction_month' => $m, 'rainfall' => 10, 'tmax' => 31, 'tmin' => 23,
                'temperature_average' => 27, 'humidity' => 78, 'status' => 'ready', 'weather_model' => 'test', 'generated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
        }

        $c = app(YieldSeasonComparison::class)->build($farmer, $farm, 'Dry');

        $this->assertTrue($c['current']['weather']['complete']);
        $this->assertSame(50.0, (float) $c['current']['weather']['rain']);
        $this->assertSame('cmp_insight_rain_drier_dry', $c['insight'][0]['key']);
        $this->actingAs($farmer)->get('/analysis/yield?season=Dry')->assertOk()
            ->assertSee('This dry season is forecast drier than the last 4.')
            ->assertSee('iClimate forecast');
    }

    public function test_comparison_endpoint_follows_farm_season_and_language_and_refuses_other_farms(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = $this->farm($farmer);
        $other = $this->farm(User::factory()->farmer()->create());

        $this->actingAs($farmer)->get(route('analysis.yield.comparison', ['farm_id' => $farm->id, 'season' => 'Wet']))
            ->assertOk()
            ->assertSee('data-season="Wet"', false)
            ->assertSee('Wet 2022')
            ->assertSee('Wet 2027')
            ->assertDontSee('<html', false);

        $this->actingAs($farmer)->get(route('analysis.yield.comparison', ['farm_id' => $other->id, 'season' => 'Wet']))->assertNotFound();

        $this->actingAs($farmer)->withSession([\App\Support\FarmerLanguage::SESSION_KEY => 'fil'])
            ->get(route('analysis.yield.comparison', ['farm_id' => $farm->id, 'season' => 'Dry']))
            ->assertOk()->assertSee('Parehong season, mga nakaraang taon')->assertSee('Tag-araw 2021–22');
    }
}
