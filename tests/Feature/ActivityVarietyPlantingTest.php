<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\RiceVariety;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityVarietyPlantingTest extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-11-25 10:00:00', 'Asia/Manila');
        $this->travelTo($this->now);
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        parent::tearDown();
    }

    private function farmerWithFarm(string $locale = 'en'): array
    {
        $farmer = User::factory()->create([
            'role' => User::ROLE_FARMER,
            'barangay' => 'Matabungkay',
            'preferred_language' => $locale,
        ]);
        FarmerProfile::factory()->create([
            'user_id' => $farmer->id,
            'barangay' => 'Matabungkay',
            'farm_area' => 2.0,
            'farm_type' => 'Irrigated',
        ]);
        $farm = Farm::query()->where('user_id', $farmer->id)->first();
        if ($farm === null) {
            $farm = new Farm([
                'user_id' => $farmer->id,
                'farm_name' => 'Farm 1',
                'is_primary' => true,
            ]);
        }
        $farm->forceFill([
            'barangay' => 'Matabungkay',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
            'verification_status' => Farm::VERIFICATION_APPROVED,
        ])->save();

        return [$farmer, $farm];
    }

    private function variety(string $name = 'NSIC Rc 222', int $maturity = 110): RiceVariety
    {
        return RiceVariety::create([
            'import_key' => strtolower(str_replace(' ', '_', $name)).'_'.uniqid(),
            'variety_name' => $name,
            'nsic_reg_no' => $name,
            'maturity_tpr_ds' => $maturity,
            'maturity_tpr_ws' => $maturity,
            'maturity_typical' => $maturity,
            'is_active' => true,
            'source' => RiceVariety::SOURCE_IMPORT,
        ]);
    }

    public function test_planted_activity_requires_variety_and_saves_variety_and_expected_harvest(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm();
        $variety = $this->variety('NSIC Rc 222', 110);

        // Missing variety fails validation
        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => '2026-11-25',
                'kind' => 'planted',
                'farm_id' => $farm->id,
            ])
            ->assertSessionHasErrors('rice_variety_id', null, 'activity');

        // Picking a variety saves rice_variety_id + computed expected harvest
        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => '2026-11-25',
                'kind' => 'planted',
                'farm_id' => $farm->id,
                'rice_variety_id' => (string) $variety->id,
            ])
            ->assertRedirect();

        $schedule = FarmerPlantingSchedule::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertSame($variety->id, $schedule->rice_variety_id);
        $this->assertSame('2027-03-15', $schedule->scheduled_harvest_date?->toDateString());

        // My Farm shows Variety column, calendar cell variety, and closing reference for Harvested
        $myFarm = $this->actingAs($farmer)->get(route('farmer.my-farm', ['month' => '2026-11']));
        $myFarm->assertOk();
        $myFarm->assertSee('NSIC Rc 222');
        $myFarm->assertSee('110 days left');
        $myFarm->assertSee('Mar 15, 2027');

        // Dashboard Days to harvest card shows variety and days left
        $dashboard = $this->actingAs($farmer)->get(route('farmer.dashboard'));
        $dashboard->assertOk();
        $dashboard->assertSee('NSIC Rc 222');
        $dashboard->assertSee('110');
    }

    public function test_live_estimate_preview_math_for_variety_past_planting_and_unknown(): void
    {
        [$farmer] = $this->farmerWithFarm();
        $variety = $this->variety('NSIC Rc 222', 110);

        // Past planting (20 days ago -> 90 days from today)
        $res = $this->actingAs($farmer)->getJson(route('rice-varieties.estimate', [
            'variety' => $variety->id,
            'date' => $this->now->subDays(20)->toDateString(),
        ]))->assertOk()->json();

        $this->assertSame(110, $res['days_from_planting']);
        $this->assertSame(90, $res['days_from_today']);
        $this->assertTrue($res['is_past_planting']);
        $this->assertStringContainsString('about 110 days from planting', $res['preview_summary']);
        $this->assertSame("That's 90 days from today.", $res['preview_from_today']);

        // "I don't know" (no variety) -> Lian default maturity, labeled estimate
        $unknown = $this->actingAs($farmer)->getJson(route('rice-varieties.estimate', [
            'date' => $this->now->toDateString(),
        ]))->assertOk()->json();

        $this->assertTrue($unknown['is_default_estimate']);
        $this->assertStringContainsString('estimate', $unknown['preview_summary']);
        $this->assertNull($unknown['preview_from_today']);
    }

    public function test_editing_planting_date_or_variety_recomputes_expected_harvest(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm();
        $v1 = $this->variety('NSIC Rc 222', 110);
        $v2 = $this->variety('NSIC Rc 480', 120);

        $this->actingAs($farmer)->post(route('farmer.activities.store'), [
            'date' => '2026-11-25',
            'kind' => 'planted',
            'farm_id' => $farm->id,
            'rice_variety_id' => (string) $v1->id,
        ])->assertRedirect();

        $schedule = FarmerPlantingSchedule::query()->where('user_id', $farmer->id)->firstOrFail();
        $this->assertSame('2027-03-15', $schedule->scheduled_harvest_date?->toDateString());

        $this->actingAs($farmer)->put(route('farmer.planting-schedules.update', $schedule), [
            'farm_id' => $farm->id,
            'planting_date' => '2026-11-25',
            'scheduled_harvest_date' => '2027-03-15',
            'season' => 'Dry',
            'farm_type' => 'Irrigated',
            'rice_variety_id' => $v2->id,
            'harvest_date_mode' => 'auto',
        ])->assertRedirect();

        $schedule->refresh();
        $this->assertSame($v2->id, $schedule->rice_variety_id);
        $this->assertSame('2027-03-25', $schedule->scheduled_harvest_date?->toDateString());
    }

    public function test_enforces_no_future_planting_and_no_overlap_per_farm(): void
    {
        [$farmer, $farm] = $this->farmerWithFarm();
        $variety = $this->variety('NSIC Rc 222', 110);

        // Future planting rejected
        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => $this->now->addDays(5)->toDateString(),
                'kind' => 'planted',
                'farm_id' => $farm->id,
                'rice_variety_id' => (string) $variety->id,
            ])
            ->assertSessionHasErrors('date', null, 'activity');

        // Valid planting today
        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => '2026-11-25',
                'kind' => 'planted',
                'farm_id' => $farm->id,
                'rice_variety_id' => (string) $variety->id,
            ])
            ->assertRedirect();

        // Second planting on same farm before expected harvest is rejected
        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => '2026-11-20',
                'kind' => 'planted',
                'farm_id' => $farm->id,
                'rice_variety_id' => 'unknown',
            ])
            ->assertSessionHasErrors('date', null, 'activity');

        // Separate farm can still plant
        $secondFarm = new Farm([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 2',
            'barangay' => 'Matabungkay',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 1.5,
        ]);
        $secondFarm->forceFill(['verification_status' => Farm::VERIFICATION_APPROVED])->save();

        $this->actingAs($farmer)
            ->post(route('farmer.activities.store'), [
                'date' => '2026-11-25',
                'kind' => 'planted',
                'farm_id' => $secondFarm->id,
                'rice_variety_id' => 'unknown',
            ])
            ->assertRedirect();

        $this->assertSame(2, FarmerPlantingSchedule::query()->where('user_id', $farmer->id)->count());
    }
}
