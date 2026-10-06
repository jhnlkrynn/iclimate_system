<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\PlantingSchedulePredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The farmer dashboard's "Add Planting Information" pop-up: what its Save & Apply button stores, and for whom. */
class FarmerPlantingInfoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-04 02:00:00', 'UTC'));       // 10:00 AM, Sunday Oct 4 in Lian
        // The yield model is slow and external; saving must not depend on it.
        $this->mock(PlantingSchedulePredictionService::class, fn ($mock) => $mock->shouldReceive('refresh')->andReturnUsing(fn ($schedule) => $schedule));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function farmer(?float $area = null): User
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Binubusan']);
        FarmerProfile::factory()->create(['user_id' => $user->id, 'barangay' => 'Binubusan', 'farm_area' => $area, 'farm_type' => 'Rainfed']);

        return $user;
    }

    private function save(User $as, array $data)
    {
        return $this->actingAs($as)->postJson(route('farmer.dashboard.planting-info'), $data);
    }

    public function test_saving_starts_a_plan_and_stores_the_farm_size_for_the_signed_in_farmer(): void
    {
        $farmer = $this->farmer();

        $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => '1.5'])
            ->assertOk()
            ->assertJson(['message' => 'Planting information saved successfully.', 'planting_date' => '2026-10-10', 'farm_area' => '1.50']);

        $plan = FarmerPlantingSchedule::query()->sole();
        $this->assertSame($farmer->id, $plan->user_id);
        $this->assertSame('2026-10-10', $plan->planting_date->toDateString());
        $this->assertSame('2027-02-02', $plan->scheduled_harvest_date->toDateString());     // the dashboard's 115 day season
        $this->assertSame('Wet', $plan->season);                                            // October is wet season (App\Support\RiceSeason)
        $this->assertSame('Rainfed', $plan->farm_type);                                     // taken from the farmer's profile
        $this->assertSame('1.50', $plan->farm_area_hectares);
        $this->assertSame('1.50', $farmer->farmerProfile()->first()->farm_area);
    }

    public function test_saving_again_updates_the_current_plan_instead_of_adding_another(): void
    {
        $farmer = $this->farmer(2.0);
        $plan = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => '2026-09-20', 'scheduled_harvest_date' => '2027-01-13',
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Binubusan', 'farm_area_hectares' => 2, 'notes' => 'Lower field',
        ]);

        $this->save($farmer, ['planting_date' => '2026-09-25', 'farm_area' => '2.75'])->assertOk();

        $this->assertSame(1, FarmerPlantingSchedule::query()->count());
        $plan->refresh();
        $this->assertSame('2026-09-25', $plan->planting_date->toDateString());
        $this->assertSame('2.75', $plan->farm_area_hectares);
        // Everything the pop-up does not ask for is left as the farmer set it.
        $this->assertSame('2027-01-13', $plan->scheduled_harvest_date->toDateString());
        $this->assertSame('Wet', $plan->season);
        $this->assertSame('Irrigated', $plan->farm_type);
        $this->assertSame('Lower field', $plan->notes);
        $this->assertSame('2.75', $farmer->farmerProfile()->first()->farm_area);
    }

    public function test_a_harvested_plan_is_left_alone_and_a_new_one_is_started(): void
    {
        $farmer = $this->farmer(1.0);
        $old = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => '2026-05-10', 'scheduled_harvest_date' => '2026-09-02',
            'season' => 'Wet', 'farm_type' => 'Rainfed', 'farm_area_hectares' => 1, 'progress_status' => 'Harvested',
        ]);

        $this->save($farmer, ['planting_date' => '2026-10-12', 'farm_area' => '1'])->assertOk();

        $this->assertSame(2, FarmerPlantingSchedule::query()->count());
        $this->assertSame('2026-05-10', $old->refresh()->planting_date->toDateString());
    }

    public function test_both_fields_are_required_with_farmer_friendly_messages(): void
    {
        $this->save($this->farmer(), [])
            ->assertStatus(422)
            ->assertJsonPath('errors.planting_date.0', 'Please select your planting date.')
            ->assertJsonPath('errors.farm_area.0', 'Please enter a valid farm size greater than 0 hectares.');

        $this->assertSame(0, FarmerPlantingSchedule::query()->count());
    }

    public function test_zero_negative_and_non_numeric_farm_sizes_are_rejected(): void
    {
        $farmer = $this->farmer(1.25);

        foreach (['0', '-1', '0.00', 'abc', '10001'] as $area) {
            $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => $area])
                ->assertStatus(422)
                ->assertJsonPath('errors.farm_area.0', 'Please enter a valid farm size greater than 0 hectares.');
        }
        $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => '1.234'])
            ->assertStatus(422)
            ->assertJsonPath('errors.farm_area.0', 'Please use up to 2 decimal places, for example 1.50.');

        $this->assertSame('1.25', $farmer->farmerProfile()->first()->farm_area);              // nothing was overwritten
        $this->assertSame(0, FarmerPlantingSchedule::query()->count());
    }

    public function test_decimal_farm_sizes_are_accepted(): void
    {
        foreach (['0.50', '1.00', '2.75', '5'] as $area) {
            $farmer = $this->farmer();
            $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => $area])->assertOk();
            $this->assertSame(number_format((float) $area, 2), $farmer->farmerProfile()->first()->farm_area);
        }
    }

    public function test_impossible_and_far_off_dates_are_rejected(): void
    {
        $farmer = $this->farmer();

        foreach (['2026-02-31', 'not-a-date', '10/10/2026', '2024-01-01', '2028-01-01'] as $date) {
            $this->save($farmer, ['planting_date' => $date, 'farm_area' => '1'])
                ->assertStatus(422)
                ->assertJsonPath('errors.planting_date.0', 'Please select a planting date within one year of today.');
        }
    }

    public function test_a_planting_date_after_the_plans_harvest_date_is_rejected(): void
    {
        $farmer = $this->farmer(1.0);
        FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => '2026-09-20', 'scheduled_harvest_date' => '2027-01-13',
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'farm_area_hectares' => 1,
        ]);

        $response = $this->save($farmer, ['planting_date' => '2027-02-01', 'farm_area' => '1'])->assertStatus(422);

        $this->assertStringContainsString('Jan 13, 2027', $response->json('errors.planting_date.0'));
        $this->assertSame('2026-09-20', FarmerPlantingSchedule::query()->sole()->planting_date->toDateString());
    }

    public function test_the_owner_is_always_the_signed_in_farmer_whatever_the_request_says(): void
    {
        $farmer = $this->farmer();
        $other = $this->farmer(4.0);

        $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => '1.5', 'user_id' => $other->id, 'farmer_id' => $other->id])->assertOk();

        $this->assertSame($farmer->id, FarmerPlantingSchedule::query()->sole()->user_id);
        $this->assertSame('4.00', $other->farmerProfile()->first()->farm_area);
    }

    public function test_only_signed_in_farmers_can_save(): void
    {
        $data = ['planting_date' => '2026-10-10', 'farm_area' => '1.5'];

        $this->postJson(route('farmer.dashboard.planting-info'), $data)->assertUnauthorized();
        foreach ([User::ROLE_MAO, User::ROLE_IT_EXPERT] as $role) {
            $this->save(User::factory()->create(['role' => $role]), $data)->assertForbidden();
        }

        $this->assertSame(0, FarmerPlantingSchedule::query()->count());
    }

    public function test_a_failed_yield_estimate_does_not_undo_the_save_or_leak_details(): void
    {
        $this->mock(PlantingSchedulePredictionService::class, fn ($mock) => $mock->shouldReceive('refresh')->andThrow(new \RuntimeException('SQLSTATE secret-model-failure')));
        $farmer = $this->farmer();

        $response = $this->save($farmer, ['planting_date' => '2026-10-10', 'farm_area' => '1.5'])->assertOk();

        $this->assertStringNotContainsString('secret-model-failure', $response->getContent());
        $this->assertSame(1, FarmerPlantingSchedule::query()->count());
    }
}
