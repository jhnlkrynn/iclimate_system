<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Analytics\DashboardOverviewService;
use App\Support\ProjectYieldDataset;
use App\Support\SampleDataDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const NO_DATA = 'No verified data available';

    private const INSUFFICIENT = 'Insufficient verified data';

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['*' => Http::response([], 500)]);
    }

    private function rice(string $barangay, int $year, float $area, float $production, ?string $month = null, array $extra = []): RiceProduction
    {
        // $extra wins over the defaults (array_merge, not +), e.g. to plant placeholder remarks.
        return RiceProduction::factory()->create(array_merge([
            'barangay' => $barangay, 'year' => $year, 'area_hectares' => $area,
            'total_production' => $production, 'yield_per_hectare' => round($production / $area, 2),
            'production_month' => $month, 'remarks' => 'Field survey record',
        ], $extra));
    }

    private function kpi(array $overview, string $key): array
    {
        $match = collect($overview['kpis'])->firstWhere('key', $key);
        $this->assertNotNull($match, "KPI [{$key}] missing");

        return $match;
    }

    private function chart(array $overview, string $id): array
    {
        $match = collect($overview['charts'])->firstWhere('id', $id);
        $this->assertNotNull($match, "Chart [{$id}] missing");

        return $match;
    }

    private function source(array $overview, string $label): array
    {
        $match = collect($overview['sources'])->firstWhere('label', $label);
        $this->assertNotNull($match, "Source [{$label}] missing");

        return $match;
    }

    public function test_every_role_shows_five_primary_kpis(): void
    {
        $service = app(DashboardOverviewService::class);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->assertCount(5, $service->farmer($farmer, null, null, null, null)['kpis']);
        $this->assertCount(5, $service->mao(null)['kpis']);
        $this->assertCount(5, $service->it()['kpis']);
    }

    /** A KPI/chart is either absent (swapped for one that has data) or says plainly that it has none. */
    private function assertEmptyOrReplaced(array $overview, string $collection, string $keyName, string $key, array $sentinels): void
    {
        $match = collect($overview[$collection])->firstWhere($keyName, $key);
        if ($match === null) {
            return;
        }
        if ($collection === 'kpis') {
            $this->assertContains($match['value'], $sentinels, "KPI [{$key}] must not show a figure without data");
        } else {
            $this->assertTrue($match['empty'], "Chart [{$key}] must be empty or replaced");
        }
    }

    public function test_unverifiable_figures_say_so_instead_of_showing_zeros(): void
    {
        HeatmapArea::query()->delete();
        $service = app(DashboardOverviewService::class);
        $mao = $service->mao(null);
        $none = [self::INSUFFICIENT, self::NO_DATA];

        $this->assertEmptyOrReplaced($mao, 'kpis', 'key', 'production', $none);
        $this->assertEmptyOrReplaced($mao, 'kpis', 'key', 'monitored', $none);
        $this->assertEmptyOrReplaced($mao, 'charts', 'id', 'production-trend', $none);
        $this->assertEmptyOrReplaced($mao, 'charts', 'id', 'production-by-barangay', $none);
        $this->assertEmptyOrReplaced($mao, 'charts', 'id', 'risk-levels', $none);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $overview = $service->farmer($farmer, null, null, null, null);
        $this->assertEmptyOrReplaced($overview, 'kpis', 'key', 'predicted-yield', $none);
        $this->assertEmptyOrReplaced($overview, 'kpis', 'key', 'current-weather', $none);
        $this->assertEmptyOrReplaced($overview, 'kpis', 'key', 'planting-advisory', $none);

        $it = $service->it();
        $this->assertEmptyOrReplaced($it, 'kpis', 'key', 'rice-records', $none);
    }

    public function test_a_kpi_without_data_is_replaced_by_another_one_that_has_data(): void
    {
        User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $service = app(DashboardOverviewService::class);
        $it = $service->it();

        // Users always exist in this test, so the IT dashboard can always offer a figure.
        foreach ($it['kpis'] as $kpi) {
            $this->assertNotSame('0', $kpi['value'], "KPI [{$kpi['key']}] must not be a misleading zero");
        }
        $this->assertCount(5, $it['kpis']);
        $this->assertCount(count(array_unique(array_column($it['kpis'], 'key'))), $it['kpis'], 'A replacement must not duplicate a KPI already shown');
    }

    public function test_yield_figures_come_from_the_project_yield_dataset_and_are_labelled(): void
    {
        $yields = app(ProjectYieldDataset::class);
        $mao = app(DashboardOverviewService::class)->mao(2024);

        $yieldKpi = $this->kpi($mao, 'yield');
        $this->assertSame(number_format($yields->yearlyMean(2024), 2), $yieldKpi['value']);
        $this->assertStringContainsString('Historical', $yieldKpi['note']);

        $chart = $this->chart($mao, 'yield-by-month');
        $this->assertFalse($chart['empty']);
        $this->assertSame($yields->monthly(2024), $chart['datasets'][0]['data']);
        $this->assertSame('Historical', $chart['basis']);
    }

    public function test_yield_change_compares_only_months_present_in_both_years(): void
    {
        $yields = app(ProjectYieldDataset::class);
        $expected = $yields->compareWithPrevious(2025);

        $this->assertNotNull($expected);
        $mao = app(DashboardOverviewService::class)->mao(2025);
        $trend = $this->kpi($mao, 'yield')['trend'];

        $this->assertSame($expected['change'], $trend['delta']);
        $this->assertSame('vs '.$expected['previous_year'], $trend['label']);
        $this->assertStringContainsString('same '.$expected['months'].' months', $this->kpi($mao, 'yield')['note']);
    }

    public function test_mao_year_filter_drives_production_kpi_and_charts_together(): void
    {
        $this->rice('Binubusan', 2023, 10, 40);
        $this->rice('Binubusan', 2024, 10, 50);
        $this->rice('Cumba', 2024, 5, 30);

        $service = app(DashboardOverviewService::class);
        $mao = $service->mao(2023);

        $this->assertSame(2023, $mao['year']);
        $this->assertSame('40.0', $this->kpi($mao, 'production')['value']);
        $this->assertSame(['2023'], $this->chart($mao, 'production-trend')['labels']);
        $this->assertSame(['Binubusan'], $this->chart($mao, 'production-by-barangay')['labels']);

        // An unknown year falls back to the latest year with verified yield/harvest data.
        $latest = $service->mao(1999);
        $this->assertSame(2025, $latest['year']);
    }

    public function test_production_change_compares_only_barangays_present_in_both_years(): void
    {
        $this->rice('Binubusan', 2023, 10, 40);
        $this->rice('Binubusan', 2024, 10, 50);
        $this->rice('Cumba', 2024, 5, 100);     // new in 2024: must not inflate the change

        $mao = app(DashboardOverviewService::class)->mao(2024);

        $this->assertSame(25.0, $this->kpi($mao, 'production')['trend']['delta']);
    }

    public function test_farmer_history_is_the_municipal_dataset_and_says_so(): void
    {
        $yields = app(ProjectYieldDataset::class);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Poblacion']);

        $overview = app(DashboardOverviewService::class)->farmer($farmer, 'Poblacion', null, null, 2024);

        $kpi = $this->kpi($overview, 'historical-yield');
        $this->assertSame(number_format($yields->meanUpTo(2024), 2), $kpi['value']);
        $this->assertStringContainsString('Lian municipality', $kpi['note']);
        $this->assertStringContainsString('Historical', $kpi['note']);
        $this->assertStringContainsString('municipality', $this->chart($overview, 'yield-trend')['subtitle']);
    }

    public function test_predicted_yield_is_a_separate_dataset_from_recorded_history(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        $prediction = [
            'predicted_yield' => 3.21, 'target_month' => '2026-09', 'confidence' => 78,
            'planting_advisory' => 'Ideal planting period.',
            'irrigation_recommendation' => 'Maintain normal irrigation monitoring.',
            'risk_level' => 'Moderate',
        ];

        $overview = app(DashboardOverviewService::class)->farmer($farmer, 'Binubusan', $prediction, null, 2025);
        $chart = $this->chart($overview, 'yield-trend');

        $this->assertSame('2026', collect($chart['labels'])->last());
        $this->assertSame('actual', $chart['datasets'][0]['kind']);
        $this->assertNull(collect($chart['datasets'][0]['data'])->last(), 'the predicted year must have no recorded value');
        $this->assertSame('predicted', $chart['datasets'][1]['kind']);
        $this->assertSame(3.21, collect($chart['datasets'][1]['data'])->last());
        $this->assertCount(1, array_filter($chart['datasets'][1]['data'], fn ($v) => $v !== null));
        $this->assertSame(count($chart['labels']), count($chart['extra']['Months on file']));

        $kpi = $this->kpi($overview, 'predicted-yield');
        $this->assertSame('3.21', $kpi['value']);
        $this->assertSame('Predicted', $kpi['status_label']);
        $this->assertStringContainsString('September 2026', $kpi['note']);

        $advisory = $this->kpi($overview, 'planting-advisory');
        $this->assertSame('Recommended', $advisory['value']);
        $this->assertStringContainsString('Irrigation:', $advisory['note']);
    }

    public function test_severe_risk_overrides_a_recommended_planting_advisory(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $prediction = ['planting_advisory' => 'Ideal planting period.', 'risk_level' => 'Severe'];

        $overview = app(DashboardOverviewService::class)->farmer($farmer, null, $prediction, null, null);

        $this->assertSame('Not recommended', $this->kpi($overview, 'planting-advisory')['value']);
    }

    public function test_weather_charts_use_ambulong_records_and_never_call_an_external_service(): void
    {
        $chart = $this->chart(app(DashboardOverviewService::class)->mao(2024), 'weather-trend');

        $this->assertFalse($chart['empty']);
        $this->assertCount(12, $chart['labels']);
        $this->assertSame('actual', $chart['datasets'][0]['kind']);
        $this->assertNotNull($chart['datasets'][0]['data'][0], 'Ambulong 2024 January rainfall should be present');
        Http::assertNothingSent();
    }

    public function test_the_current_weather_kpi_names_its_source_and_data_time(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $weather = [
            'success' => true, 'provider' => 'Open-Meteo', 'fetched_at' => '2026-09-29T17:00:00+00:00',
            'current' => ['temperature_c' => 24.6, 'humidity_percent' => 96, 'wind_kph' => 6],
            'today' => ['rainfall_mm' => 3.0],
        ];

        $kpi = $this->kpi(app(DashboardOverviewService::class)->farmer($farmer, null, null, $weather, null), 'current-weather');

        $this->assertSame('24.6', $kpi['value']);
        $this->assertStringContainsString('Online weather service', $kpi['note']);   // plain wording on the farmer page
        $this->assertStringNotContainsString('Open-Meteo', $kpi['note']);
        $this->assertStringContainsString('updated 1:00 AM', $kpi['note']);   // 17:00 UTC = 01:00 Asia/Manila
    }

    public function test_it_overview_counts_real_records(): void
    {
        User::factory()->count(2)->create(['role' => User::ROLE_FARMER]);
        User::factory()->create(['role' => User::ROLE_MAO]);
        $this->rice('Binubusan', 2024, 10, 50);
        AnalyticsRun::query()->create([
            'uuid' => (string) Str::uuid(), 'type' => 'rice_yield_prediction', 'status' => 'completed',
            'cache_key' => 'k', 'input' => [],
        ]);

        $overview = app(DashboardOverviewService::class)->it();

        $this->assertSame('3', $this->kpi($overview, 'users')['value']);
        $this->assertSame('1', $this->kpi($overview, 'rice-records')['value']);
        $this->assertSame('1', $this->kpi($overview, 'prediction-records')['value']);
        $this->assertSame(3, array_sum($this->chart($overview, 'users-by-role')['datasets'][0]['data']));
        $this->assertCount(30, $this->chart($overview, 'system-activity')['labels']);
    }

    public function test_every_chart_says_what_kind_of_data_it_shows(): void
    {
        $service = app(DashboardOverviewService::class);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        foreach ([$service->farmer($farmer, null, null, null, null), $service->mao(null), $service->it()] as $overview) {
            foreach ($overview['charts'] as $chart) {
                $this->assertNotEmpty($chart['basis'], "Chart [{$chart['id']}] has no data-kind label");
            }
        }
    }

    /* ------------------------------------------------------- source panels */

    public function test_each_dashboard_lists_its_sources_with_kind_source_and_last_updated(): void
    {
        $service = app(DashboardOverviewService::class);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $mao = $service->mao(null);
        $yield = $this->source($mao, 'Rice yield history');
        $this->assertSame('Historical', $yield['kind']);
        $this->assertSame(60, $yield['records']);
        $this->assertNotNull($yield['updated']);
        $this->assertStringContainsString('iClimate modeling dataset', $yield['source']);

        $this->assertSame('Predicted', $this->source($mao, 'Prediction records')['kind']);
        $this->assertSame('Historical', $this->source($mao, 'Monthly climate history')['kind']);
        $this->assertNotNull($mao['generated_at'] ?? $service->mao(null)['charts']);

        $prediction = ['predicted_yield' => 3.21, 'target_date' => '2026-09-29', 'model' => 'Random Forest', 'model_version' => 'v-test', 'inputs_used' => ['rainfall' => 210.5], 'planting_advisory' => 'Ideal planting period.'];
        $weather = ['success' => true, 'provider' => 'Open-Meteo', 'fetched_at' => '2026-09-29T17:00:00+00:00'];
        $farmerOverview = $service->farmer($farmer, null, $prediction, $weather, null);
        $this->assertSame('Observed', $this->source($farmerOverview, 'Current weather')['kind']);
        $this->assertSame('2026-09-29T17:00:00+00:00', $this->source($farmerOverview, 'Current weather')['updated']);
        $this->assertSame('Predicted', $this->source($farmerOverview, 'Predicted rice yield')['kind']);
        $this->assertStringContainsString('rainfall 210.5', $this->source($farmerOverview, 'Predicted rice yield')['note']);
        $this->assertSame('Calculated', $this->source($farmerOverview, 'Planting advisory & irrigation')['kind']);

        $it = $service->it();
        $this->assertSame('Count', $this->source($it, 'Users')['kind']);
        $this->assertSame('Calculated', $this->source($it, 'System & data status')['kind']);
    }

    /* ------------------------------------------------------- sample data */

    public function test_lorem_placeholder_text_is_recognised_and_real_prose_is_not(): void
    {
        $this->assertTrue(SampleDataDetector::looksLikeLorem('Harum quas vero non odit quam ut.'));
        $this->assertTrue(SampleDataDetector::looksLikeLorem('Aspernatur dolorum ratione est nemo labore alias. Neque et cum nemo nulla.'));
        $this->assertFalse(SampleDataDetector::looksLikeLorem('Field survey completed by MAO staff in September.'));
        $this->assertFalse(SampleDataDetector::looksLikeLorem('Heavy rainfall expected in Lian, Batangas tomorrow.'));
        $this->assertFalse(SampleDataDetector::looksLikeLorem(null));
    }

    public function test_seeded_sample_rows_are_kept_out_of_every_figure_but_never_deleted(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        $seededAt = now()->subDays(60);

        // Seeder batch: placeholder remarks, all created in the same second.
        $sample = $this->rice('Lumaniag', 2024, 10, 999, null, ['remarks' => 'Harum quas vero non odit quam ut.', 'created_at' => $seededAt, 'updated_at' => $seededAt]);
        $real = $this->rice('Binubusan', 2024, 10, 50, null, ['created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);
        Cache::flush();

        $mao = app(DashboardOverviewService::class)->mao(2024);

        $this->assertSame('50.0', $this->kpi($mao, 'production')['value'], 'the 999-ton sample row must not be counted');
        $this->assertSame(['Binubusan'], $this->chart($mao, 'production-by-barangay')['labels']);
        $this->assertSame('1', $this->kpi(app(DashboardOverviewService::class)->it(), 'rice-records')['value']);

        $warning = $this->source($mao, 'Rice production records')['warning'];
        $this->assertStringContainsString('1 of 2', $warning);
        $this->assertStringContainsString('excluded', $warning);

        // Nothing was removed.
        $this->assertDatabaseHas('rice_productions', ['id' => $sample->id]);
        $this->assertDatabaseHas('rice_productions', ['id' => $real->id]);
    }

    public function test_impossible_climate_readings_are_excluded_and_counted_separately(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        \App\Models\ClimateRecord::factory()->create(['record_date' => now()->addYear()->toDateString(), 'rainfall' => 5000, 'temperature' => 100, 'wind_speed' => 500, 'source' => 'sir mags']);
        \App\Models\ClimateRecord::factory()->create(['record_date' => now()->subMonth()->toDateString(), 'rainfall' => 120, 'temperature' => 28, 'humidity' => 80, 'wind_speed' => 12, 'source' => 'PAGASA']);
        Cache::flush();

        $detector = app(SampleDataDetector::class);
        $this->assertCount(1, $detector->implausibleClimateIds());

        $it = app(DashboardOverviewService::class)->it();
        $weather = $this->kpi($it, 'weather-records');
        $this->assertStringContainsString('1 verified database records', $weather['note']);
        $this->assertStringContainsString('1 excluded', $weather['note']);
    }

    public function test_the_audit_runs_read_only_and_the_purge_defaults_to_a_dry_run(): void
    {
        config(['iclimate.exclude_sample_data' => true]);
        $at = now()->subDays(30);
        $sample = $this->rice('Lumaniag', 2024, 10, 999, null, ['remarks' => 'Harum quas vero non odit quam ut.', 'created_at' => $at, 'updated_at' => $at]);

        $this->assertSame(0, Artisan::call('iclimate:data-audit'));
        $this->assertStringContainsString('seeded-sample 1', Artisan::output());

        $this->assertSame(0, Artisan::call('iclimate:purge-sample-data'));
        $this->assertStringContainsString('Dry run', Artisan::output());
        $this->assertDatabaseHas('rice_productions', ['id' => $sample->id]);
    }

    /* ------------------------------------------------------------ pages */

    public function test_dashboards_render_charts_sources_and_year_filter_only_for_the_right_roles(): void
    {
        $this->rice('Binubusan', 2023, 10, 40);
        $this->rice('Binubusan', 2024, 10, 50);

        // MAO Dashboard (2026-10): four tiles and one chart; no shared overview strip and no year filter.
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($mao)->get(route('mao.dashboard'))
            ->assertOk()
            ->assertSee('data-mao-kpis', false)
            ->assertSee('Rice Yield by Year')
            ->assertDontSee('data-dashboard-overview="mao"', false)
            ->assertDontSee('id="dashYear"', false)
            ->assertDontSee('Figures last calculated');

        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $this->actingAs($it)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('data-it-dashboard', false)
            ->assertSee('System Health')
            ->assertSee('Users by Role')
            ->assertDontSee('id="dashYear"', false);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        $this->actingAs($farmer)->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('data-farmer-dashboard', false)
            ->assertSee('Weather today')
            ->assertDontSee('My Farm Today')
            ->assertDontSee("Today's Highlights")
            ->assertDontSee('Data sources &amp; last updated', false)     // replaced by a small information link
            ->assertDontSee('Users by Role')
            ->assertDontSee('Production by Barangay');
    }
}
