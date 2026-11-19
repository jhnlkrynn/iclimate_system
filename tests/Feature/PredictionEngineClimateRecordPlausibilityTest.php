<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Prediction\PredictionDateValidator;
use App\Services\Prediction\PredictionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regression coverage for the 2026-09 PredictionEngine data-quality fix:
 * PredictionEngine::buildModelInput() must exclude physically-impossible
 * ClimateRecord rows (via ClimateRecord::scopePlausible()) from the derived
 * "previous_*" / "*_6m" / "*_3m" / "seasonal_*" features fed to the rice-yield
 * model, without any hardcoded row-ID exclusion.
 */
class PredictionEngineClimateRecordPlausibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // The climate inputs follow the target month (verification 2026-11-18): run inside the observed station
        // record (2015-01 .. 2025-12) so the months before the default target (next month) are observed.
        $this->travelTo('2025-02-10 09:00:00');
        \App\Support\YieldModelClimateWindow::forget();

        Config::set('services.groq.enabled', false);
        Config::set('services.farming_ai.url', 'http://farming-ai.test');

        Http::fake([
            'farming-ai.test/predict' => function (Request $request) {
                return Http::response([
                    'rice_yield_prediction' => [
                        'predicted_yield' => 3.5,
                        'unit' => 'tons/hectare',
                    ],
                    'confidence_score' => 88,
                ]);
            },
        ]);
    }

    public function test_implausible_row_id_analog_of_row_6_does_not_influence_derived_features(): void
    {
        // Target 2025-03 (now 2025-02-10 + 1 month). Five older plausible rows and the latest one cover the six
        // months before it (T-6 .. T-1); an entered month takes the place of the station month it covers.
        foreach (['2024-09-15', '2024-10-15', '2024-11-15', '2024-12-15', '2025-01-15'] as $date) {
            ClimateRecord::query()->create(['record_date' => $date, 'rainfall' => 100.0, 'temperature' => 27.0, 'humidity' => 75.0, 'wind_speed' => 7.0, 'season' => ClimateRecord::SEASON_DRY, 'source' => 'test fixture']);
        }

        // The latest plausible historical row.
        ClimateRecord::query()->create([
            'record_date' => '2025-02-05',   // not after today (2025-02-10): a future date is implausible
            'rainfall' => 120.0,
            'temperature' => 28.0,
            'humidity' => 75.0,
            'wind_speed' => 8.0,
            'season' => ClimateRecord::SEASON_DRY,
            'source' => 'test fixture',
        ]);

        // A fresh analog of the historical "sir mags" bad row (ID 6): future
        // date, 5000mm rainfall, 100C, 500km/h wind. This is a NEW row (not
        // ID 6) to prove the fix generalizes rather than hardcoding an ID.
        $badRow = ClimateRecord::factory()->create([
            'record_date' => now()->addYear()->toDateString(),
            'rainfall' => 5000.0,
            'temperature' => 100.0,
            'humidity' => 50.0,
            'wind_speed' => 500.0,
            'season' => ClimateRecord::SEASON_DRY,
        ]);

        // Sanity: the bad row really is excluded by the scope, and really is
        // the most recent by record_date (so ->latest()->first() would pick
        // it if the scope were not applied).
        $this->assertFalse(ClimateRecord::query()->plausible()->whereKey($badRow->id)->exists());
        $this->assertSame($badRow->id, ClimateRecord::query()->latest('record_date')->first()->id);

        $modelInput = $this->buildModelInputFor();

        // previous_* must reflect the plausible row (120mm/28C/8km-wind), not
        // the bad row's 5000mm/100C/500.
        $this->assertSame(120.0, $modelInput['previous_rainfall']);
        $this->assertSame(28.0, $modelInput['previous_temp']);
        $this->assertSame(8.0, $modelInput['previous_wind_speed']);
        $this->assertSame(103.33, $modelInput['rainfall_6m'], 'mean of the six entered months, never the bad row');
    }

    public function test_a_different_implausible_row_not_id_6_is_also_excluded(): void
    {
        ClimateRecord::query()->create([
            'record_date' => '2025-01-10',
            'rainfall' => 90.0,
            'temperature' => 27.0,
            'humidity' => 70.0,
            'wind_speed' => 6.0,
            'season' => ClimateRecord::SEASON_WET,
            'source' => 'test fixture',
        ]);

        // A different implausible row: impossible humidity is out of scope
        // for this model's bounds (only temp/wind/rainfall/date are bounded
        // per the audited controller rules), so use an out-of-range
        // temperature instead to prove no ID is special-cased.
        $anotherBadRow = ClimateRecord::factory()->create([
            'record_date' => '2025-02-01',
            'rainfall' => 50.0,
            'temperature' => 999.0,
            'humidity' => 60.0,
            'wind_speed' => 5.0,
            'season' => ClimateRecord::SEASON_WET,
        ]);

        $this->assertFalse(ClimateRecord::query()->plausible()->whereKey($anotherBadRow->id)->exists());

        $plausibleLatest = ClimateRecord::query()->plausible()->latest('record_date')->first();
        $this->assertNotNull($plausibleLatest);
        $this->assertNotSame($anotherBadRow->id, $plausibleLatest->id);
    }

    public function test_without_six_entered_records_the_station_record_supplies_the_history_never_the_excluded_row(): void
    {
        // Only an implausible row exists — the plausible-filtered set is empty.
        ClimateRecord::factory()->create([
            'record_date' => now()->addYear()->toDateString(),
            'rainfall' => 5000.0,
            'temperature' => 100.0,
            'humidity' => 50.0,
            'wind_speed' => 500.0,
        ]);

        $this->assertTrue(ClimateRecord::query()->plausible()->doesntExist());

        $modelInput = $this->buildModelInputFor();

        // No entered month: the history inputs are the six Ambulong station months BEFORE the target month
        // (2025-03 -> 2025-02 .. 2024-09; verification 2026-11-18, previously "the latest six on file"). They are
        // real observations: not 0, not the forecast copied in their place, never the excluded row.
        $byMonth = collect(app(\App\Services\Climate\AmbulongHistoricalClimateDataset::class)->load()['observations'])
            ->keyBy(fn (array $obs): string => sprintf('%04d-%02d', $obs['year'], $obs['month']));
        $months = collect(['2025-02', '2025-01', '2024-12', '2024-11', '2024-10', '2024-09'])->map(fn (string $m): array => $byMonth[$m]);

        $this->assertSame(round((float) $months->first()['rainfall'], 2), $modelInput['previous_rainfall'], 'previous = T-1');
        $this->assertSame(round((float) $months->avg('rainfall'), 2), $modelInput['rainfall_6m'], 'a six-month mean, as trained');
        $this->assertSame(round((float) $months->avg('temperature'), 2), $modelInput['temp_6m']);
        $this->assertLessThan(60, $modelInput['previous_temp']);
        $this->assertLessThan(5000, $modelInput['rainfall_6m']);
    }

    public function test_municipality_request_uses_a_real_municipality_area_when_no_production_rows_exist(): void
    {
        $this->assertGreaterThan(0, app(PredictionEngine::class)->municipalityArea());
    }

    /**
     * @return array<string, mixed>
     */
    private function buildModelInputFor(): array
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);
        FarmerProfile::query()->create([
            'user_id' => $user->id,
            'full_name' => $user->name,
            'farm_area' => 2.0,
            'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            'barangay' => 'Balibago',
        ]);

        $engine = app(PredictionEngine::class);
        $result = $engine->predict(
            PredictionDateValidator::defaultTargetDate(),
            FarmerProfile::FARM_TYPE_RAINFED,
            2.0,
            $user,
        );

        return $result['model_input'];
    }
}
