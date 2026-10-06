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
        // Five older plausible rows, so the entered records cover the full six-month window (with fewer, the
        // engine reads the Ambulong station record instead; see the last test).
        foreach (['2025-08-15', '2025-09-15', '2025-10-15', '2025-11-15', '2025-12-15'] as $date) {
            ClimateRecord::query()->create(['record_date' => $date, 'rainfall' => 100.0, 'temperature' => 27.0, 'humidity' => 75.0, 'wind_speed' => 7.0, 'season' => ClimateRecord::SEASON_DRY, 'source' => 'test fixture']);
        }

        // The latest plausible historical row.
        ClimateRecord::query()->create([
            'record_date' => '2026-01-15',
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
        $this->assertLessThan(1000, $modelInput['rainfall_6m']);
    }

    public function test_a_different_implausible_row_not_id_6_is_also_excluded(): void
    {
        ClimateRecord::query()->create([
            'record_date' => '2026-02-10',
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
            'record_date' => '2026-03-01',
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

        // Fewer than six entered records: the history inputs are the latest six months of the verified
        // Ambulong station record (2026-10 fix: the yield forecast could never run on a new install). They
        // are real observations: not 0, not the forecast copied in their place, never the excluded row.
        $months = collect(app(\App\Services\Climate\AmbulongHistoricalClimateDataset::class)->load()['observations'])
            ->sortByDesc(fn (array $obs): int => $obs['year'] * 12 + $obs['month'])->take(6)->values();

        $this->assertSame(round((float) $months->first()['rainfall'], 2), $modelInput['previous_rainfall']);
        $this->assertSame(round((float) $months->sum('rainfall'), 2), $modelInput['rainfall_6m']);
        $this->assertSame(round((float) $months->avg('temperature'), 2), $modelInput['temp_6m']);
        $this->assertLessThan(60, $modelInput['previous_temp']);
        $this->assertLessThan(5000, $modelInput['rainfall_6m']);
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
