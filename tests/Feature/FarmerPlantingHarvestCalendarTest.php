<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\PlantingSchedulePredictionService;
use App\Services\Prediction\RiceYieldPredictionService;
use App\Services\WeatherApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FarmerPlantingHarvestCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_can_create_edit_and_delete_a_schedule_with_non_blocking_date_warnings(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->for($farmer)->create([
            'barangay' => 'Binubusan',
            'farm_type' => FarmerProfile::FARM_TYPE_RAINFED,
            'farm_area' => null,
        ]);

        $this->actingAs($farmer)->post(route('farmer.planting-schedules.store'), [
            'planting_date' => '2027-01-10',
            'harvest_month' => '2027-08',
            'scheduled_harvest_date' => '',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
            'barangay' => 'Binubusan',
            'farm_area_hectares' => '',
            'progress_status' => '',
            'notes' => 'North field.',
        ])->assertRedirect(route('calendar.index'))
            ->assertSessionHas('schedule_warnings');

        $schedule = FarmerPlantingSchedule::query()->sole();
        $this->assertSame($farmer->id, $schedule->user_id);
        $this->assertSame('Farm area is missing. Add the farm area before generating a yield prediction.', $schedule->prediction_unavailable_reason);

        $this->actingAs($farmer)->put(route('farmer.planting-schedules.update', $schedule), [
            'planting_date' => '2027-05-10',
            'scheduled_harvest_date' => '2027-10-20',
            'harvest_month' => '',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => 'Binubusan',
            'farm_area_hectares' => '',
            'progress_status' => 'Planted',
            'notes' => 'Updated field note.',
        ])->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('farmer_planting_schedules', [
            'id' => $schedule->id,
            'farm_type' => 'Irrigated',
            'progress_status' => 'Planted',
            'notes' => 'Updated field note.',
        ]);

        $this->actingAs($farmer)
            ->delete(route('farmer.planting-schedules.destroy', $schedule))
            ->assertRedirect(route('calendar.index'));
        $this->assertDatabaseMissing('farmer_planting_schedules', ['id' => $schedule->id]);
    }

    public function test_farmer_cannot_change_another_farmers_schedule(): void
    {
        $owner = User::factory()->farmer()->create();
        $other = User::factory()->farmer()->create();
        $schedule = FarmerPlantingSchedule::query()->create([
            'user_id' => $owner->id,
            'planting_date' => '2027-05-10',
            'harvest_month' => '2027-10-01',
            'season' => 'Wet',
            'farm_type' => 'Rainfed',
        ]);

        $this->actingAs($other)->get(route('farmer.planting-schedules.edit', $schedule))->assertForbidden();
        $this->actingAs($other)->delete(route('farmer.planting-schedules.destroy', $schedule))->assertForbidden();
    }

    public function test_mao_dashboard_displays_and_filters_farmer_schedules(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $ana = User::factory()->farmer()->create(['name' => 'Ana Farmer']);
        $ben = User::factory()->farmer()->create(['name' => 'Ben Farmer']);
        FarmerPlantingSchedule::query()->create([
            'user_id' => $ana->id,
            'planting_date' => '2027-05-10',
            'scheduled_harvest_date' => '2027-10-15',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'barangay' => 'Binubusan',
            'predicted_yield' => 3.5,
            'historical_same_month_average' => 4.0,
            'yield_difference' => -0.5,
            'comparison_interpretation' => 'lower',
            'recommended_actions' => 'Validate the field and check irrigation status.',
            'prediction_confidence' => 82,
        ]);
        FarmerPlantingSchedule::query()->create([
            'user_id' => $ben->id,
            'planting_date' => '2027-11-10',
            'harvest_month' => '2028-04-01',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'barangay' => 'Lumaniag',
        ]);

        $this->mock(WeatherApiService::class)->shouldReceive('forecast')->andReturnNull();

        $response = $this->actingAs($mao)->get(route('mao.operations', [
            'schedule_month' => 10,
            'schedule_season' => 'Wet',
            'schedule_barangay' => 'Binubusan',
            'schedule_farm_type' => 'Irrigated',
            'schedule_farmer' => $ana->id,
            'schedule_status' => 'upcoming_planting',
        ]))->assertOk()
            ->assertSee('Farmer Planting and Harvest Calendar')
            ->assertSee('Ana Farmer')
            ->assertSee('Recommended Actions');
        $this->assertCount(1, $response->viewData('farmerSchedules'));
        $this->assertSame('Ana Farmer', $response->viewData('farmerSchedules')->first()->farmer->name);
    }

    public function test_prediction_is_compared_only_with_matching_previous_same_month_history(): void
    {
        $farmer = User::factory()->farmer()->create();
        FarmerProfile::factory()->for($farmer)->create(['farm_area' => 2.5]);
        $schedule = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'planting_date' => '2026-01-10',
            'scheduled_harvest_date' => '2027-03-15',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'barangay' => 'Binubusan',
            'farm_area_hectares' => 2.5,
        ]);
        foreach ([[2025, 4.0, '2025-03'], [2026, 2.0, '2026-03'], [2024, 9.0, '2024-04']] as [$year, $yield, $month]) {
            RiceProduction::factory()->create([
                'year' => $year,
                'production_month' => $month,
                'yield_per_hectare' => $yield,
                'barangay' => 'Binubusan',
                'season' => 'Dry',
                'irrigation_type' => 'Rainfed',
            ]);
        }

        $predictor = Mockery::mock(RiceYieldPredictionService::class);
        $predictor->shouldReceive('predictForUser')->once()->andReturn([
            'predicted_yield' => 2.5,
            'confidence' => 80,
            'fallback' => false,
            'source' => 'Test trained model',
            'model' => 'Random Forest',
            'model_version' => 'test',
            'model_input' => ['rainfall' => 50, 'temp_avg' => 30, 'humidity' => 80, 'wind_speed' => 5],
        ]);

        (new PlantingSchedulePredictionService($predictor))->refresh($schedule);
        $schedule->refresh();

        $this->assertSame('3.00', $schedule->historical_same_month_average);
        $this->assertSame('-0.50', $schedule->yield_difference);
        $this->assertSame('lower', $schedule->comparison_interpretation);
        $this->assertStringContainsString('Low rainfall', $schedule->recommended_actions);
    }

    public function test_prediction_with_no_matching_history_uses_the_safe_history_message(): void
    {
        $farmer = User::factory()->farmer()->create();
        $schedule = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id,
            'planting_date' => '2026-01-10',
            'harvest_month' => '2027-03-01',
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'barangay' => 'Matabungkay',
            'farm_area_hectares' => 1.5,
        ]);
        $predictor = Mockery::mock(RiceYieldPredictionService::class);
        $predictor->shouldReceive('predictForUser')->once()->andReturn([
            'predicted_yield' => 4.2,
            'confidence' => null,
            'fallback' => false,
            'source' => 'Test trained model',
            'model_input' => ['rainfall' => 180, 'temp_avg' => 29],
        ]);

        (new PlantingSchedulePredictionService($predictor))->refresh($schedule);
        $schedule->refresh();

        $this->assertNull($schedule->historical_same_month_average);
        $this->assertSame('insufficient_history', $schedule->comparison_interpretation);
        $this->assertSame('No previous yield history available for comparison. No previous same-month history available.', $schedule->comparison_note);
        $this->assertSame('needs_review', $schedule->monitoringStatus());
    }

    public function test_dashboards_and_calendar_do_not_crash_if_feature_migration_is_temporarily_missing(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);
        $farmer = User::factory()->farmer()->create();
        Schema::drop('farmer_planting_schedules');
        $this->mock(WeatherApiService::class)->shouldReceive('forecast')->andReturnNull();

        $this->actingAs($mao)
            ->get(route('mao.operations'))
            ->assertOk()
            ->assertSee('Farm calendar is temporarily unavailable');

        $this->actingAs($farmer)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Farmer schedules are temporarily unavailable');

        $this->actingAs($farmer)
            ->get(route('farmer.planting-schedules.create'))
            ->assertRedirect(route('calendar.index'));

        $this->actingAs($farmer)
            ->get(route('farmer.planting-schedules.edit', 999))
            ->assertRedirect(route('calendar.index'));
    }
}
