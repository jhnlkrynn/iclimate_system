<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Prediction\BarangayYieldForecastService;
use App\Services\Prediction\PredictionOrchestrator;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\YieldPredictionStore;
use App\Services\Risk\HeatMapLayerService;
use App\Services\Weather\LianBarangayWeatherService;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

/**
 * The Heat Map's Expected yield layer (/api/heat-map?layer=yield&period=week|2weeks): the area-weighted average of
 * each barangay's farm estimates, a model run built like a farm's only for barangays with no farms, for This Week /
 * 2 Weeks with that window of the saved forecast, saved in yield_predictions per period (so a cache clear loses
 * nothing) and never run while a page waits. The farmer dashboard's season estimate stays its own period.
 */
class HeatMapYieldLayerTest extends TestCase
{
    use RefreshDatabase;

    private const MONTHLY = ['rainfall' => 237.31, 'temperature' => 27.78];

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-18 09:00', 'Asia/Manila'));
        $orchestrator = Mockery::mock(PredictionOrchestrator::class);
        $orchestrator->shouldReceive('monthlyWeatherForecast')->andReturn(['predictions' => self::MONTHLY]);
        $this->app->instance(PredictionOrchestrator::class, $orchestrator);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /** 16 saved forecast days from today for every barangay: 1 mm a day (3 mm in Cumba), 24-32 °C. */
    private function seedForecasts(): void
    {
        $records = [];
        foreach (LianBarangays::all() as $name) {
            $records[$name] = ['barangay' => $name, 'fetched_at' => now()->toIso8601String(), 'daily' => collect(range(0, 15))->map(fn (int $i): array => [
                'date' => CarbonImmutable::now('Asia/Manila')->addDays($i)->toDateString(), 'rainfall' => $name === 'Cumba' ? 3 : 1, 'temperature_min' => 24, 'temperature_max' => 32,
            ])->all()];
        }
        Cache::forever(LianBarangayWeatherService::STORE_KEY, ['records' => $records, 'status' => []]);
    }

    /** A farm with its saved season estimate, as iclimate:warm-farmer-yields stores it from the farmer dashboard. */
    private function farmWithEstimate(string $barangay, float $hectares, float $yield): Farm
    {
        $farmer = User::factory()->farmer()->create(['status' => User::STATUS_ACTIVE]);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'Farm '.$farmer->id, 'barangay' => $barangay, 'farm_size_hectares' => $hectares, 'farm_size_method' => Farm::SIZE_EXACT, 'is_primary' => true]);
        app(YieldPredictionStore::class)->saveFarm($farm, ['yield_tons_per_hectare' => $yield, 'model_input' => ['area' => $hectares], 'model_version' => 'rice_yield_model_final:test', 'target_month' => '2026-10', 'ran_at' => now('Asia/Manila')->toIso8601String()], 'Wet season 2026');

        return $farm;
    }

    /**
     * The model: the season run (no rainfall given) answers $season; a week / 2 weeks run answers 4.40 - rainfall/1000,
     * so a wetter window reads lower. Every call is recorded; none may come from a page.
     */
    private function fakeModel(float $season = 3.9): \Mockery\MockInterface
    {
        $model = Mockery::mock(RiceYieldPredictionService::class);
        $model->shouldReceive('modelSource')->andReturn('storage/models/rice_yield_model_final.pkl');
        $model->shouldReceive('predictForUser')->andReturnUsing(fn ($date, string $farmType, ?float $area, ?User $user, array $overrides) => [
            'success' => true,
            'yield_tons_per_hectare' => isset($overrides['rainfall']) ? round(4.40 - $overrides['rainfall'] / 1000, 2) : $season,
            'model_version' => 'rice_yield_model_final:test', 'target_month' => $date->format('Y-m'),
            'model_input' => ['area' => $area, 'farm_type' => $farmType] + $overrides,
        ]);
        $this->app->instance(RiceYieldPredictionService::class, $model);

        return $model;
    }

    /** @return array<string, array<string, mixed>> the API rows by name */
    private function api(string $period = 'week'): array
    {
        $json = $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO]))->getJson('/api/heat-map?layer=yield&period='.$period)->assertOk();
        $this->assertSame($period, $json->json('period'));
        $this->assertStringContainsString($period === 'week' ? "this week's forecast" : "next 2 weeks' forecast", $json->json('source'));

        return collect($json->json('barangays'))->keyBy('name')->all();
    }

    public function test_the_week_window_is_its_forecast_days_plus_the_monthly_share_of_the_rest(): void
    {
        $forecast = ['daily' => collect(range(0, 15))->map(fn (int $i): array => ['date' => CarbonImmutable::now('Asia/Manila')->addDays($i)->toDateString(), 'rainfall' => 2, 'temperature_min' => 24, 'temperature_max' => 30])->all()];
        $service = app(BarangayYieldForecastService::class);

        // October 18: this week is Oct 18-24 (7 days); 2 weeks is Oct 18-31 (14 days, the forecast's November days not counted).
        $week = $service->periodWeather($forecast, self::MONTHLY, 7, CarbonImmutable::now('Asia/Manila'));
        $this->assertSame(7, $week['forecast_days']);
        $this->assertSame(round(14 + 237.31 * 24 / 31, 2), $week['overrides']['rainfall']);
        $this->assertSame(round((27 * 7 + 27.78 * 24) / 31, 2), $week['overrides']['temp_avg']);
        $twoWeeks = $service->periodWeather($forecast, self::MONTHLY, 14, CarbonImmutable::now('Asia/Manila'));
        $this->assertSame(14, $twoWeeks['forecast_days']);
        $this->assertSame(round(28 + 237.31 * 17 / 31, 2), $twoWeeks['overrides']['rainfall']);
        // No forecast: no estimate rather than a guess.
        $this->assertNull($service->periodWeather([], self::MONTHLY, 7, CarbonImmutable::now('Asia/Manila')));
    }

    public function test_each_period_takes_the_farms_average_first_and_its_own_run_only_without_farms(): void
    {
        $this->seedForecasts();
        $this->farmWithEstimate('Humayingan', 2.0, 4.0);
        $this->farmWithEstimate('HUMAYNGAN', 1.0, 3.1);   // a typo still counts for Humayingan
        $model = $this->fakeModel(3.9);
        $service = app(BarangayYieldForecastService::class);

        $service->prepare();
        $week = $service->preparePeriod('week');
        $service->preparePeriod('2weeks');

        // Two farms (same weather, different sizes) and the 18 barangays without farms; one call per distinct input.
        $this->assertSame(2, $week['farm_runs']);
        $this->assertSame(2 + 18, $week['model_calls']);
        foreach (['season', 'week', '2weeks'] as $period) {
            $this->assertSame(19, YieldPrediction::query()->where('scope', YieldPrediction::SCOPE_BARANGAY)->where('period', $period)->count(), $period);
        }

        $rows = $this->api('week');
        // This week in Humayingan: 7 x 1 mm + 237.31 x 24/31 = 190.72 mm -> 4.40 - 0.19 = 4.21 for both farms.
        $this->assertEquals(['t_ha' => 4.21, 'class' => 'fair', 'basis' => 'farm_average', 'farms_used' => 2, 'season_t_ha' => 3.7, 'vs_season' => 0.51],
            array_intersect_key($rows['Humayingan']['yield'], array_flip(['t_ha', 'class', 'basis', 'farms_used', 'season_t_ha', 'vs_season'])));
        // Cumba has no farms: its own run, with its own (wetter) forecast: 21 mm + 183.71 = 204.71 -> 4.20.
        $this->assertEquals(['t_ha' => 4.2, 'basis' => 'barangay_model', 'farms_used' => 0, 'season_t_ha' => 3.9], array_intersect_key($rows['Cumba']['yield'], array_flip(['t_ha', 'basis', 'farms_used', 'season_t_ha'])));
        $this->assertSame('humayingan', $rows['Humayingan']['key']);

        // 2 weeks: 14 mm + 237.31 x 17/31 = 144.14 -> 4.26.
        $this->assertSame(4.26, $this->api('2weeks')['Humayingan']['yield']['t_ha']);
        // The farmer dashboard's season estimate is untouched.
        $this->assertSame(3.7, $service->all()['Humayingan']['predicted_yield']);
    }

    public function test_saved_estimates_survive_a_cache_clear_and_a_page_never_runs_the_model(): void
    {
        $this->seedForecasts();
        $this->farmWithEstimate('Humayingan', 3.0, 4.14);
        $this->fakeModel();
        app(BarangayYieldForecastService::class)->prepare();
        app(BarangayYieldForecastService::class)->preparePeriod('week');

        Cache::flush();
        Http::fake();
        $model = Mockery::mock(RiceYieldPredictionService::class);
        $model->shouldReceive('modelSource')->andReturn('storage/models/rice_yield_model_final.pkl');
        $model->shouldNotReceive('predictForUser');
        $this->app->instance(RiceYieldPredictionService::class, $model);
        app(HeatMapLayerService::class)->forgetCache();

        $this->assertSame(4.21, $this->api('week')['Humayingan']['yield']['t_ha']);
        $this->assertNull($this->api('2weeks')['Humayingan']['yield']['t_ha']);   // not prepared: no estimate, nothing run
        Http::assertNothingSent();
    }

    public function test_without_saved_estimates_every_barangay_says_no_estimate_yet(): void
    {
        foreach (['week', '2weeks'] as $period) {
            $rows = $this->api($period);
            $this->assertCount(19, $rows);
            foreach ($rows as $row) {
                $this->assertSame('unavailable', $row['level']);
                $this->assertSame('No estimate yet', $row['label']);
                $this->assertNull($row['yield']['t_ha']);
            }
        }
    }

    public function test_farm_barangay_names_are_normalized_and_unplaceable_farms_are_listed(): void
    {
        $poblacion = $this->farmWithEstimate('BARANGAY 1 POB', 1.0, 4.0);
        $blank = $this->farmWithEstimate('—', 1.0, 4.0);

        $this->assertSame('Barangay 1 (Pob.)', YieldPrediction::query()->where('farm_id', $poblacion->id)->value('barangay'));
        $this->assertNull(YieldPrediction::query()->where('farm_id', $blank->id)->value('barangay'));
        $this->assertSame([$blank->id], app(BarangayYieldForecastService::class)->unmatchedFarms()->pluck('id')->all());
    }
}
