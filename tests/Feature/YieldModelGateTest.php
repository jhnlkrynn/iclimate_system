<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Models\YieldPrediction;
use App\Services\Analytics\FarmerDashboardService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\YieldModelGate;
use App\Services\Prediction\YieldPredictionStore;
use App\Services\Weather\TyphoonSafetyService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Audit 2026-11-18 P1-3: ACCURACY NOT VERIFIED — estimates outside the model's training range are not shown. */
class YieldModelGateTest extends TestCase
{
    use RefreshDatabase;

    /** The shape of every input the application sends today (stored model_input, 2026-11-17). */
    private const DEPLOYED_INPUT = ['area' => 1.4, 'rainfall_6m' => 1982.9, 'temp_range' => 1.15, 'rainfall' => 90.14, 'temp_avg' => 28.52, 'season' => 'Dry'];

    private const IN_RANGE_INPUT = ['area' => 120.0, 'rainfall_6m' => 180.0, 'temp_range' => 7.5, 'rainfall' => 90.14, 'temp_avg' => 28.52, 'season' => 'Dry'];

    private function gate(array $result, array $input): array
    {
        $method = new \ReflectionMethod(RiceYieldPredictionService::class, 'applyGate');

        return $method->invoke(app(RiceYieldPredictionService::class), $result, $input);
    }

    private function estimate(float $yield = 4.13): array
    {
        return ['success' => true, 'predicted_yield' => $yield, 'yield_tons_per_hectare' => $yield, 'predicted_yield_raw' => $yield,
            'predicted_yield_tons_per_hectare' => $yield, 'estimated_total_production_tons' => $yield * 1.4, 'confidence' => 85,
            'planting_advisory' => 'Plant after the first heavy rain.'];
    }

    public function test_todays_inputs_are_outside_the_training_range_and_in_range_inputs_are_not(): void
    {
        $this->assertSame(['compatible' => false, 'outside' => ['area', 'rainfall_6m', 'temp_range'], 'within_range' => false], YieldModelGate::assess(self::DEPLOYED_INPUT));
        $this->assertSame(['compatible' => false, 'outside' => [], 'within_range' => true], YieldModelGate::assess(self::IN_RANGE_INPUT), 'in range, accuracy not verified');
        config(['prediction.rice_yield.accuracy_verified' => true]);
        $this->assertSame(['compatible' => true, 'outside' => [], 'within_range' => true], YieldModelGate::assess(self::IN_RANGE_INPUT));
        config(['prediction.rice_yield.accuracy_verified' => false]);
        $this->assertFalse(YieldModelGate::assess(null)['compatible'], 'an input that cannot be checked is not treated as validated');

        $extremeWeather = array_merge(self::IN_RANGE_INPUT, ['seasonal_rainfall' => 2400.0, 'temp_avg' => 35.0]);
        $assessed = YieldModelGate::assess($extremeWeather);
        $this->assertFalse($assessed['within_range']);
        $this->assertContains('temp_avg', $assessed['outside']);
        $this->assertContains('seasonal_rainfall', $assessed['outside']);
    }

    public function test_withhold_is_the_default_and_removes_the_number_but_keeps_the_rest(): void
    {
        $this->assertSame('withhold', YieldModelGate::mode());
        $result = $this->gate($this->estimate(), self::DEPLOYED_INPUT);

        $this->assertFalse($result['success']);
        $this->assertSame('withheld', $result['availability']);
        $this->assertNull($result['predicted_yield']);
        $this->assertNull($result['yield_tons_per_hectare']);
        $this->assertNull($result['estimated_total_production_tons']);
        $this->assertNull($result['confidence']);
        $this->assertSame(4.13, $result['predicted_yield_unvalidated'], 'kept for audit, never displayed');
        $this->assertStringContainsString('accuracy not verified', $result['unavailable_reason']);
        $this->assertSame('Plant after the first heavy rain.', $result['planting_advisory'], 'weather-based advice is not the yield model');
        $this->assertFalse($result['accuracy_verified']);
    }

    public function test_label_mode_keeps_the_number_marked_unvalidated_and_in_range_estimates_pass(): void
    {
        config(['prediction.rice_yield.gate' => 'label']);
        $labelled = $this->gate($this->estimate(), self::DEPLOYED_INPUT);
        $this->assertSame(4.13, $labelled['predicted_yield']);
        $this->assertSame('outside_training_range', $labelled['validation_status']);

        config(['prediction.rice_yield.gate' => 'withhold']);
        $held = $this->gate($this->estimate(4.05), self::IN_RANGE_INPUT);
        $this->assertNull($held['predicted_yield'], 'in range but accuracy not verified: still withheld');
        $this->assertSame('within_training_range', $held['validation_status']);
        $this->assertSame('withheld', $held['availability']);

        config(['prediction.rice_yield.accuracy_verified' => true]);
        $inRange = $this->gate($this->estimate(4.05), self::IN_RANGE_INPUT);
        $this->assertSame(4.05, $inRange['predicted_yield']);
        $this->assertSame('within_training_range', $inRange['validation_status']);
        $this->assertFalse($inRange['accuracy_verified'], 'inside the range is still not verified accuracy');
    }

    public function test_stored_history_is_preserved_but_not_displayed(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'North', 'barangay' => 'Prenza', 'is_primary' => true]);
        $store = app(YieldPredictionStore::class);
        $store->saveFarm($farm, ['predicted_yield' => 4.13, 'model_input' => self::DEPLOYED_INPUT, 'model_version' => 'rf-final']);
        $store->saveBarangay('Prenza', ['predicted_yield' => 4.12, 'basis' => 'farm_average', 'farms_used' => 1]);

        $this->assertSame(1, YieldPrediction::query()->where('scope', 'farm')->count(), 'saved as history');
        $this->assertCount(0, $store->farms());
        $this->assertCount(0, $store->barangays());

        config(['prediction.rice_yield.gate' => 'off']);
        $this->assertCount(1, $store->farms());
        $this->assertCount(1, $store->barangays());
    }

    public function test_the_farmer_card_says_why_instead_of_showing_an_old_unvalidated_number(): void
    {
        Http::fake(['*' => Http::response([], 500)]);
        $this->mock(TyphoonSafetyService::class, fn ($mock) => $mock->shouldReceive('activeEvent')->andReturn(null));
        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn(['success' => false, 'availability' => 'withheld']));
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Prenza']);
        FarmerProfile::factory()->create(['user_id' => $farmer->id, 'barangay' => 'Prenza', 'farm_area' => 1.4, 'farm_type' => 'Irrigated']);
        Cache::flush();
        $farmId = (int) Farm::query()->where('user_id', $farmer->id)->where('is_primary', true)->value('id');
        // An estimate cached before the gate existed: the same unvalidated model made it.
        Cache::forever(FarmerDashboardService::predictionCacheKey($farmer->id, $farmId), $this->estimate() + [
            'model_input' => self::DEPLOYED_INPUT, 'target_month' => now('Asia/Manila')->format('Y-m'), 'ran_at' => CarbonImmutable::now('Asia/Manila')->toIso8601String(),
        ]);

        $html = $this->actingAs($farmer)->get('/farmer/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString(__('farmer.home.harvest.unvalidated'), html_entity_decode($html));
        $this->assertStringNotContainsString('4.13 t/ha', $html);
    }

    public function test_all_eleven_numeric_ood_checks_and_schedule_unavailable_reason(): void
    {
        $allOod = [
            'area' => 0.5, 'rainfall_6m' => 10.0, 'temp_range' => 2.0,
            'rainfall' => 0.5, 'temp_avg' => 36.0, 'previous_rainfall' => 1.0,
            'previous_temp' => 35.0, 'temp_3m' => 35.0, 'temp_6m' => 35.0,
            'seasonal_rainfall' => 50.0, 'seasonal_temp' => 35.0, 'season' => 'Dry',
        ];
        $assessed = YieldModelGate::assess($allOod);
        $this->assertFalse($assessed['within_range']);
        $this->assertCount(11, $assessed['outside']);

        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $farm = Farm::query()->create(['user_id' => $farmer->id, 'farm_name' => 'South', 'barangay' => 'Prenza', 'farm_size_hectares' => 1.2, 'is_primary' => true]);
        $schedule = \App\Models\FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'planting_date' => now('Asia/Manila')->toDateString(),
            'scheduled_harvest_date' => now('Asia/Manila')->addDays(110)->toDateString(),
            'harvest_month' => now('Asia/Manila')->addDays(110)->format('Y-m'),
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'barangay' => 'Prenza',
            'farm_area_hectares' => 1.2,
            'progress_status' => 'planted',
        ]);

        $this->mock(RiceYieldPredictionService::class, fn ($mock) => $mock->shouldReceive('predictForUser')->andReturn([
            'success' => false,
            'availability' => 'withheld',
            'predicted_yield' => null,
            'confidence' => null,
            'api_error' => null,
            'unavailable_reason' => YieldModelGate::REASON,
        ]));

        $updated = app(\App\Services\PlantingSchedulePredictionService::class)->refresh($schedule);
        $this->assertNull($updated->predicted_yield);
        $this->assertNull($updated->prediction_confidence);
        $this->assertSame(YieldModelGate::REASON, $updated->prediction_unavailable_reason);
    }
}
