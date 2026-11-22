<?php

namespace Tests\Unit;

use App\Jobs\RunYieldPredictionRequest;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmTypeYieldOverride;
use App\Models\RiceProduction;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\Prediction\SeasonYield;
use App\Services\Prediction\YieldFarmTypeAdjustment;
use App\Services\Prediction\YieldLocalAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class YieldFarmTypeAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private function seedHarvests(string $farmType, int $dryCount, float $dryYield, int $wetCount, float $wetYield): void
    {
        $farmer = User::factory()->farmer()->create();
        for ($i = 0; $i < $dryCount; $i++) {
            FarmerPlantingSchedule::query()->create([
                'user_id' => $farmer->id,
                'barangay' => 'Prenza',
                'season' => 'Dry',
                'farm_type' => $farmType,
                'planting_date' => '2026-01-10',
                'harvested_on' => '2026-04-20',
                'actual_yield' => $dryYield,
            ]);
        }
        for ($i = 0; $i < $wetCount; $i++) {
            FarmerPlantingSchedule::query()->create([
                'user_id' => $farmer->id,
                'barangay' => 'Prenza',
                'season' => 'Wet',
                'farm_type' => $farmType,
                'planting_date' => '2025-06-15',
                'harvested_on' => '2025-10-01',
                'actual_yield' => $wetYield,
            ]);
        }
    }

    public function test_returns_factor_one_when_below_harvest_or_season_thresholds(): void
    {
        $service = app(YieldFarmTypeAdjustment::class);

        // 0 records
        $empty = $service->forRequest('Rainfed', 'Dry', 4.10);
        $this->assertSame(1.0, $empty['factor']);
        $this->assertSame('none', $empty['source']);
        $this->assertSame(0, $empty['counts']['Rainfed']);

        // 9 rainfed + 10 irrigated across 2 seasons -> still below 10 for rainfed
        $this->seedHarvests('Rainfed', 5, 3.2, 4, 3.0);
        $this->seedHarvests('Irrigated', 5, 4.4, 5, 4.2);

        $belowCount = $service->forRequest('Rainfed', 'Dry', 4.10);
        $this->assertSame(1.0, $belowCount['factor']);
        $this->assertSame('none', $belowCount['source']);
        $this->assertSame(9, $belowCount['counts']['Rainfed']);

        // 10 rainfed in only 1 season -> still below 2 seasons
        $this->seedHarvests('Rainfed', 1, 3.2, 0, 0); // now 6 Dry, 4 Wet = 2 seasons; test pure 1 season separately
        FarmerPlantingSchedule::query()->delete();
        $this->seedHarvests('Rainfed', 10, 3.2, 0, 0);
        $this->seedHarvests('Irrigated', 10, 4.4, 0, 0);

        $oneSeason = $service->forRequest('Rainfed', 'Dry', 4.10);
        $this->assertSame(1.0, $oneSeason['factor']);
        $this->assertSame('none', $oneSeason['source']);
        $this->assertSame(1, $oneSeason['seasons']['Rainfed']);
    }

    public function test_computes_factor_from_recorded_and_verified_records_and_clamps_to_range(): void
    {
        // Unverified rice_productions must be ignored
        RiceProduction::query()->create([
            'barangay' => 'Luyahan',
            'irrigation_type' => 'Rainfed',
            'season' => 'Dry',
            'year' => 2025,
            'area_hectares' => 10,
            'total_production' => 90,
            'yield_per_hectare' => 9.0,
            'verified_at' => null,
        ]);

        // 10 Rainfed across 2 seasons (Dry=3.0), 9 Irrigated schedules + 1 verified RiceProduction (Dry=5.0)
        $this->seedHarvests('Rainfed', 5, 3.0, 5, 3.0);
        $this->seedHarvests('Irrigated', 4, 5.0, 5, 5.0);
        RiceProduction::query()->forceCreate([
            'barangay' => 'Prenza',
            'irrigation_type' => 'Irrigated',
            'season' => 'Dry',
            'year' => 2026,
            'area_hectares' => 10,
            'total_production' => 50,
            'yield_per_hectare' => 5.0,
            'verified_at' => now(),
        ]);

        $service = app(YieldFarmTypeAdjustment::class);
        // Dry season: 5 Rainfed @ 3.0 + 5 Irrigated @ 5.0 => overall avg = 4.0; Rainfed factor = 3.0/4.0 = 0.75; Irrigated = 5.0/4.0 = 1.25
        $rainfed = $service->forRequest('Rainfed', 'Dry', 4.00);
        $irrigated = $service->forRequest('Irrigated', 'Dry', 4.00);

        $this->assertSame('records', $rainfed['source']);
        $this->assertSame(0.75, $rainfed['factor']);
        $this->assertSame(3.00, $rainfed['adjusted_yield']);
        $this->assertSame('From 20 iClimate harvest records', $rainfed['basis']);

        $this->assertSame('records', $irrigated['source']);
        $this->assertSame(1.25, $irrigated['factor']);
        $this->assertSame(5.00, $irrigated['adjusted_yield']);

        // Clamp test: extreme difference (Rainfed=1.0 vs Irrigated=9.0 => overall 5.0 => raw 0.2 and 1.8)
        FarmerPlantingSchedule::query()->delete();
        RiceProduction::query()->delete();
        $this->seedHarvests('Rainfed', 5, 1.0, 5, 1.0);
        $this->seedHarvests('Irrigated', 5, 9.0, 5, 9.0);

        $clampedLow = $service->forRequest('Rainfed', 'Dry', 4.00);
        $clampedHigh = $service->forRequest('Irrigated', 'Dry', 4.00);
        $this->assertSame(0.7, $clampedLow['factor']);
        $this->assertSame(1.3, $clampedHigh['factor']);
    }

    public function test_mao_override_applies_when_below_threshold_and_records_take_priority(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'email_verified_at' => now()]);

        // Note is required
        $this->actingAs($mao)->post(route('mao.associations.yield-override.store'), [
            'rainfed_diff_percent' => -15,
            'note' => '',
        ])->assertSessionHasErrors('note');

        $this->actingAs($mao)->post(route('mao.associations.yield-override.store'), [
            'rainfed_diff_percent' => -15,
            'note' => 'MAO 2025 field survey',
        ])->assertRedirect();

        $service = app(YieldFarmTypeAdjustment::class);
        $rainfed = $service->forRequest('Rainfed', 'Dry', 4.00);
        $irrigated = $service->forRequest('Irrigated', 'Dry', 4.00);

        $this->assertSame('mao', $rainfed['source']);
        $this->assertSame(0.85, $rainfed['factor']);
        $this->assertSame(3.40, $rainfed['adjusted_yield']);
        $this->assertSame('Set by MAO', $rainfed['basis']);
        $this->assertSame('MAO 2025 field survey', $rainfed['mao_note']);
        $this->assertSame(1.0, $irrigated['factor']);

        // Once enough iClimate harvest records exist, computed factor takes priority over MAO override
        $this->seedHarvests('Rainfed', 5, 3.6, 5, 3.6);
        $this->seedHarvests('Irrigated', 5, 4.4, 5, 4.4);

        $priority = $service->forRequest('Rainfed', 'Dry', 4.00);
        $this->assertSame('records', $priority['source']);
        $this->assertSame(0.9, $priority['factor']);
        $this->assertSame('From 20 iClimate harvest records', $priority['basis']);
    }

    public function test_run_yield_prediction_request_applies_farm_type_adjustment_after_local_and_stores_details(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Luyahan']);
        FarmTypeYieldOverride::query()->create([
            'rainfed_diff_percent' => -10,
            'note' => 'MAO rainfed note',
            'updated_by' => $farmer->id,
        ]);

        $req = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Luyahan',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'area_hectares' => 2.0,
            'target_month' => '2027-03-01',
            'status' => YieldPredictionRequest::QUEUED,
        ]);

        $model = Mockery::mock(RiceYieldPredictionService::class);
        $model->shouldReceive('predictForUser')->once()->andReturn([
            'success' => true,
            'yield_tons_per_hectare' => 4.00,
        ]);

        $local = Mockery::mock(YieldLocalAdjustment::class);
        $local->shouldReceive('forRequest')->once()->andReturn([
            'model_value' => 4.00,
            'factor' => 1.0,
            'adjusted_yield' => 4.00,
            'record_count' => 0,
            'seasons_used' => 0,
        ]);

        (new RunYieldPredictionRequest($req->id))->handle(
            $model,
            app(SeasonYield::class),
            $local,
            app(YieldFarmTypeAdjustment::class),
        );

        $req->refresh();
        $this->assertSame(YieldPredictionRequest::DONE, $req->status);
        $this->assertSame(3.60, (float) $req->predicted_yield);
        $this->assertSame(4.0, (float) $req->details['farm_type_model_value']);
        $this->assertSame(0.9, (float) $req->details['farm_type_factor']);
        $this->assertSame('Set by MAO', $req->details['farm_type_basis']);
        $this->assertSame('mao', $req->details['farm_type_source']);
    }
}
