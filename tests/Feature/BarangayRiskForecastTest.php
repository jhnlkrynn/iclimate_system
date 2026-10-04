<?php

namespace Tests\Feature;

use App\Models\FarmerProfile;
use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Services\MachineLearning\MonthlyWeatherRandomForest;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\PreparesPredictions;
use Tests\TestCase;

class BarangayRiskForecastTest extends TestCase
{
    use PreparesPredictions;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        // A month without a stored forecast only queues generation; the request itself never trains.
        Bus::fake();
    }

    private function forecast(): array
    {
        return $this->getJson('/api/lian/risk-forecast')->assertOk()->json();
    }

    public function test_it_returns_the_current_month_and_the_next_five_with_every_barangay_once(): void
    {
        $this->preparePredictions(8);

        $json = $this->forecast();
        $start = CarbonImmutable::now('Asia/Manila')->startOfMonth();

        $this->assertSame('ok', $json['status']);
        $this->assertSame(
            collect(range(0, 5))->map(fn (int $i): string => $start->addMonthsNoOverflow($i)->format('Y-m'))->all(),
            array_column($json['months'], 'month'),
        );
        foreach ($json['months'] as $month) {
            $names = array_column($month['barangays'], 'barangay');
            $this->assertEqualsCanonicalizing(LianBarangays::all(), $names);
            $this->assertCount(19, array_unique($names));
            $this->assertSame(19, array_sum($month['summary']['counts']));
            $this->assertSame(19, $month['summary']['monitored']);
        }
    }

    public function test_months_without_a_ready_forecast_are_left_out_instead_of_filled_in(): void
    {
        $this->preparePredictions(3);
        $failed = CarbonImmutable::now('Asia/Manila')->startOfMonth()->addMonthsNoOverflow(1);
        MonthlyWeatherPrediction::query()
            ->where('prediction_year', $failed->year)->where('prediction_month', $failed->month)
            ->update(['status' => MonthlyWeatherPrediction::STATUS_UNREADY]);

        $months = array_column($this->forecast()['months'], 'month');

        $this->assertCount(2, $months);
        $this->assertNotContains($failed->format('Y-m'), $months);
    }

    public function test_no_forecast_at_all_reports_unavailable_without_colours(): void
    {
        $json = $this->forecast();

        $this->assertSame('unavailable', $json['status']);
        $this->assertSame([], $json['months']);
        $this->assertSame('Climate forecast information for this period is currently unavailable.', $json['message']);
    }

    public function test_risk_comes_from_the_stored_forecast_through_the_existing_scorer(): void
    {
        // A very dry forecast: the existing rules call this drought stress for every barangay.
        $this->preparePredictions(6, ['rainfall' => 60.0, 'season' => 'Dry']);

        $month = $this->forecast()['months'][0];

        $this->assertSame(60, $month['weather']['rainfall_mm']);
        $this->assertSame('Very little rain is expected', $month['summary']['main_concern']);
        $this->assertGreaterThan(0, $month['summary']['attention']);
        foreach ($month['barangays'] as $entry) {
            $this->assertContains($entry['risk_level'], ['Low', 'Moderate', 'High', 'Severe']);
            $this->assertNotEmpty($entry['action']);
        }
    }

    public function test_trend_is_omitted_for_the_first_month_and_follows_the_forecast_after_that(): void
    {
        $this->preparePredictions(6);
        // Second month turns very dry, so risk rises there.
        $second = CarbonImmutable::now('Asia/Manila')->startOfMonth()->addMonthsNoOverflow(1);
        MonthlyWeatherPrediction::query()
            ->where('prediction_year', $second->year)->where('prediction_month', $second->month)
            ->where('weather_model', MonthlyWeatherRandomForest::MODEL_VERSION)
            ->update(['rainfall' => 60.0, 'season' => 'Dry']);

        [$first, $next] = $this->forecast()['months'];

        $this->assertNull($first['summary']['trend']);
        $this->assertNull($first['barangays'][0]['trend']);
        $this->assertSame('increasing', $next['summary']['trend']);
        $this->assertContains('increasing', array_column($next['barangays'], 'trend'));
        $this->assertCount(6, $next['barangays'][0]['timeline']);
    }

    public function test_each_barangay_reports_its_known_factors_without_the_placeholder_exposure_value(): void
    {
        $this->preparePredictions(6);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Bungahan', 'farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED]);

        $month = $this->forecast()['months'][0];
        $byName = collect($month['barangays'])->keyBy('barangay');

        $this->assertSame(['farm_type' => FarmerProfile::FARM_TYPE_IRRIGATED, 'farm_type_from_records' => true, 'yield_record' => null], $byName['Bungahan']['factors']);
        // No farmer records: the documented Rainfed default is used and labelled as assumed, and no yield is invented.
        $this->assertSame(['farm_type' => FarmerProfile::FARM_TYPE_RAINFED, 'farm_type_from_records' => false, 'yield_record' => null], $byName['Cumba']['factors']);
        // The prepared test forecasts carry no confidence label, so none is invented.
        $this->assertNull($month['confidence_level']);
        $this->assertStringNotContainsString('exposure', strtolower(json_encode($month['barangays'])));
    }

    public function test_past_their_freshness_window_forecasts_are_still_read_in_one_pass_without_queueing_jobs(): void
    {
        $this->preparePredictions(6, [], fresh: false);

        $this->assertCount(6, $this->forecast()['months']);
        Bus::assertNothingDispatched();
    }

    public function test_heat_map_page_says_the_weather_forecast_covers_lian_as_a_whole(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($farmer)->get(route('heat-map.index'))
            ->assertOk()
            ->assertSee('Barangay Climate Risk Map')
            ->assertSee('Current and monthly rice-farming risk conditions across Lian, Batangas.')
            ->assertSee('Monthly weather forecasts apply to Lian, Batangas as a whole.')
            ->assertSee('About this forecast')
            ->assertSee('iClimate Random Forest monthly forecast model')
            ->assertSee('Ambulong weather station records used by iClimate')
            ->assertSee('Lian, Batangas (whole municipality)')
            ->assertSee('This is not a PAGASA forecast.')
            ->assertSee('Why are barangay risks different?')
            ->assertSee('Current Conditions')
            // The placeholder exposure rating is never presented as a validated hazard figure.
            ->assertDontSee('Hazard Score')
            ->assertDontSee('Vulnerability Rating')
            ->assertDontSee('Barangay Weather Forecast');
    }

    public function test_farmers_get_the_simple_forecast_panel_and_staff_keep_the_detailed_one(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($farmer)->get(route('heat-map.index'))
            ->assertOk()
            ->assertSee('data-forecast-audience="farmer"', false);

        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get(route('heat-map.index'))
                ->assertOk()
                ->assertSee('data-forecast-audience="staff"', false);
        }
    }

    public function test_heat_map_page_shows_forecast_controls_but_dashboard_maps_do_not(): void
    {
        Http::fake(['api.open-meteo.com/*' => Http::response([], 500)]);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'San Diego']);

        $this->actingAs($farmer)->get(route('heat-map.index'))
            ->assertOk()
            ->assertSee('data-risk-forecast', false)
            ->assertSee('data-forecast-url="'.url('/api/lian/risk-forecast').'"', false)
            ->assertSee('Forecast Month')
            ->assertSee('data-highlight-barangay="San Diego"', false);

        $this->actingAs($farmer)->get(route('farmer.dashboard'))
            ->assertOk()
            ->assertSee('id="barangayRiskMap"', false)
            ->assertDontSee('data-risk-forecast', false);
    }
}
