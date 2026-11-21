<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Prediction\RiceYieldPredictionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FarmerYieldPredictionPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(RiceYieldPredictionService::class, function ($mock): void {
            $mock->shouldReceive('predictForUser')->andReturn([
                'success' => true,
                'yield_tons_per_hectare' => 4.1,
                'predicted_yield' => 4.1,
                'uncertainty' => 0.3,
            ]);
        });
    }

    public function test_one_farm_shows_plain_text_and_dropdown_only_appears_with_two_or_more_farms(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm1 = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 29.96,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => true,
        ]);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Farm 1 · Humayingan')
            ->assertSee('Humayingan · Rainfed · 29.96 ha')
            ->assertSee('Edit farm')
            ->assertSee('Get my estimate')
            ->assertDontSee('id="ypFarm"', false)
            ->assertDontSee('Irrigation');

        Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 2',
            'barangay' => 'Prenza',
            'farm_type' => 'Irrigated',
            'farm_size_hectares' => 2.5,
            'farm_size_method' => Farm::SIZE_EXACT,
            'is_primary' => false,
        ]);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('id="ypFarm"', false)
            ->assertSee('Farm 2 · Prenza');
    }

    public function test_server_uses_selected_farm_fields_and_ignores_posted_overrides(): void
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Prenza']);
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 29.96,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
            'barangay' => 'Balibago',
            'farm_type' => 'Irrigated',
            'area_hectares' => '999',
        ])->assertRedirect('/analysis/yield');

        $record = YieldPredictionRequest::query()->sole();
        $this->assertSame($farm->id, $record->farm_id);
        $this->assertSame('Humayingan', $record->barangay);
        $this->assertSame('Rainfed', $record->farm_type);
        $this->assertEquals(29.96, (float) $record->area_hectares);
    }

    public function test_another_farmers_farm_id_is_rejected(): void
    {
        $farmer = User::factory()->farmer()->create();
        $other = User::factory()->farmer()->create();
        $otherFarm = Farm::query()->create(['user_id' => $other->id, 'farm_name' => 'Other Farm', 'barangay' => 'Prenza', 'farm_type' => 'Irrigated']);

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $otherFarm->id,
            'season' => 'Wet',
        ])->assertForbidden();

        $this->assertSame(0, YieldPredictionRequest::query()->count());
    }

    public function test_missing_size_is_required_and_saved_when_checked(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => null,
            'farm_size_method' => Farm::SIZE_UNKNOWN,
        ]);

        $this->actingAs($farmer)->from('/analysis/yield')->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
        ])->assertRedirect('/analysis/yield')->assertSessionHasErrors('area_hectares');

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
            'area_hectares' => '3.45',
            'save_size_to_farm' => '1',
        ])->assertRedirect('/analysis/yield');

        $this->assertEquals(3.45, (float) $farm->fresh()->farm_size_hectares);
        $this->assertSame(Farm::SIZE_EXACT, $farm->fresh()->farm_size_method);
    }

    public function test_result_card_displays_plain_language_tons_sacks_math_and_useful_feedback(): void
    {
        config(['prediction.rice_yield.accuracy_verified' => true]);
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 29.96,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $prediction = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Humayingan',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'area_hectares' => 29.96,
            'target_month' => '2027-03-01',
            'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => 4.10,
            'total_tons' => 122.84,
            'historical_average' => 4.16,
            'impact_level' => 'Normal',
            'completed_at' => now(),
        ]);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Dry season 2026–2027 · Farm 1 (Humayingan)')
            ->assertSee('About 4.1 tons per hectare')
            ->assertSee('(≈ 82 sacks of 50 kg)')
            ->assertSee('About 123 tons for your whole farm')
            ->assertSee('(≈ 2,457 sacks of 50 kg)')
            ->assertSee('Close to Lian\'s usual 4.16 t/ha')
            ->assertSee('Early estimate. It updates as the season\'s weather comes in.')
            ->assertSee('Was this useful?')
            ->assertSee('Season &amp; farm', false);

        $this->actingAs($farmer)->post("/analysis/yield/{$prediction->id}/useful", ['useful' => '1'])
            ->assertRedirect('/analysis/yield');
        $this->assertTrue($prediction->fresh()->useful);
    }

    public function test_old_predictions_without_farm_id_are_not_backfilled_and_still_show_in_history(): void
    {
        config(['prediction.rice_yield.accuracy_verified' => true]);
        $farmer = User::factory()->farmer()->create();
        Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 2.0,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $legacy = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Balibago',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'area_hectares' => 1.5,
            'target_month' => '2026-09-01',
            'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => 4.5,
            'total_tons' => 6.75,
            'impact_level' => 'Normal',
            'completed_at' => now()->subDay(),
        ]);

        $this->assertNull($legacy->fresh()->farm_id);
        $this->assertNull($legacy->fresh()->useful);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Wet season 2026 · Farm (Balibago)')
            ->assertSee('4.5 t/ha')
            ->assertSee('7 t');
    }

    public function test_result_card_shows_forecast_or_fallback_basis_line(): void
    {
        config(['prediction.rice_yield.accuracy_verified' => true]);
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 5.0,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $prediction = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Humayingan',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'area_hectares' => 5.0,
            'target_month' => '2027-03-01',
            'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => 4.1,
            'total_tons' => 20.5,
            'historical_average' => 4.16,
            'impact_level' => 'Normal',
            'details' => ['fallback' => false],
            'completed_at' => now(),
        ]);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Based on the weather forecast and past harvests')
            ->assertDontSee('Based on Lian\'s past harvests (forecast not yet available)');

        $prediction->forceFill(['details' => ['fallback' => true]])->save();

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Based on Lian\'s past harvests (forecast not yet available)')
            ->assertDontSee('Based on the weather forecast and past harvests');
    }

    public function test_repeat_request_within_24_hours_refreshes_existing_row_unless_inputs_or_forecast_change(): void
    {
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 5.0,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
        ])->assertRedirect('/analysis/yield');

        $first = YieldPredictionRequest::query()->sole();
        $earlier = now()->subHours(2);
        $first->forceFill(['created_at' => $earlier, 'updated_at' => $earlier, 'completed_at' => $earlier])->save();

        // Repeat within 24h with same inputs and forecast: refreshes timestamp, no new row.
        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
        ])->assertRedirect('/analysis/yield');

        $this->assertSame(1, YieldPredictionRequest::query()->count());
        $this->assertTrue($first->fresh()->created_at->gt($earlier));

        // Changed forecast hash on existing row: creates a new row.
        $first->forceFill(['details' => ['forecast_hash' => 'outdated-forecast']])->save();

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
        ])->assertRedirect('/analysis/yield');

        $this->assertSame(2, YieldPredictionRequest::query()->count());

        // Changed farm input (area): creates a new row.
        $farm->forceFill(['farm_size_hectares' => 7.5])->save();

        $this->actingAs($farmer)->post('/analysis/yield', [
            'farm_id' => $farm->id,
            'season' => 'Dry',
        ])->assertRedirect('/analysis/yield');

        $this->assertSame(3, YieldPredictionRequest::query()->count());
    }

    public function test_result_card_shows_lian_wide_note_or_local_adjustment(): void
    {
        config(['prediction.rice_yield.accuracy_verified' => true]);
        $farmer = User::factory()->farmer()->create();
        $farm = Farm::query()->create([
            'user_id' => $farmer->id,
            'farm_name' => 'Farm 1',
            'barangay' => 'Humayingan',
            'farm_type' => 'Rainfed',
            'farm_size_hectares' => 5.0,
            'farm_size_method' => Farm::SIZE_EXACT,
        ]);

        $prediction = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'farm_id' => $farm->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Humayingan',
            'season' => 'Dry',
            'farm_type' => 'Rainfed',
            'area_hectares' => 5.0,
            'target_month' => '2027-03-01',
            'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => 4.1,
            'total_tons' => 20.5,
            'historical_average' => 4.16,
            'impact_level' => 'Normal',
            'details' => ['fallback' => false, 'factor' => 1.0, 'model_value' => 4.1],
            'completed_at' => now(),
        ]);

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('This is a Lian-wide estimate for the season; it doesn\'t yet reflect your barangay or irrigation.')
            ->assertDontSee('Adjusted for Humayingan');

        $prediction->forceFill([
            'predicted_yield' => 3.9,
            'total_tons' => 19.5,
            'details' => ['fallback' => false, 'factor' => 0.9512, 'model_value' => 4.1, 'record_count' => 6, 'seasons_used' => 3],
        ])->save();

        $this->actingAs($farmer)->get('/analysis/yield')
            ->assertOk()
            ->assertSee('Adjusted for Humayingan rainfed farms: 3.9 t/ha')
            ->assertSee('Lian-wide model estimate: 4.1 t/ha');
    }
}
