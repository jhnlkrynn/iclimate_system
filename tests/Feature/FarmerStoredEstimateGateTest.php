<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verification 2026-11-18: saved model estimates (planting schedules, finished yield requests, the survey about them)
 * were shown to Farmers as "Unvalidated estimate" numbers although the gate withholds every live estimate. Under
 * withhold they stay hidden until the owner confirms accuracy; the stored rows themselves are never changed.
 */
class FarmerStoredEstimateGateTest extends TestCase
{
    use RefreshDatabase;

    private const SCHEDULE_YIELD = '4.65';

    private const REQUEST_YIELD = '3.95';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(['*' => Http::response(['error' => 'unavailable'], 500)]);
        config(['prediction.rice_yield.gate' => 'withhold', 'prediction.rice_yield.accuracy_verified' => false]);
    }

    /** @return array{0: User, 1: FarmerPlantingSchedule, 2: YieldPredictionRequest} */
    private function farmerWithSavedEstimates(): array
    {
        $farmer = User::factory()->farmer()->create(['barangay' => 'Bungahan']);
        $schedule = FarmerPlantingSchedule::query()->create([
            'user_id' => $farmer->id, 'planting_date' => now()->subMonths(5)->toDateString(), 'scheduled_harvest_date' => now()->subMonth()->toDateString(),
            'harvested_on' => now()->subMonth()->toDateString(), 'season' => 'Wet', 'farm_type' => 'Rainfed', 'barangay' => 'Bungahan',
            'farm_area_hectares' => 1.5, 'predicted_yield' => (float) self::SCHEDULE_YIELD, 'actual_yield' => 4.4,
        ]);
        $request = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id, 'scope' => YieldPredictionRequest::SCOPE_FARM, 'barangay' => 'Bungahan', 'season' => 'Wet', 'farm_type' => 'Rainfed',
            'area_hectares' => 2, 'target_month' => now()->subMonths(2)->startOfMonth()->toDateString(), 'status' => YieldPredictionRequest::DONE,
            'predicted_yield' => (float) self::REQUEST_YIELD, 'total_tons' => 7.9, 'impact_level' => 'Favorable', 'completed_at' => now(),
        ]);

        return [$farmer, $schedule, $request];
    }

    /** @return list<string> pages a Farmer opens that list saved estimates */
    private function pages(YieldPredictionRequest $request): array
    {
        return ['/calendar', '/farmer/my-reports', '/analysis/yield', '/farmer/survey?prediction='.$request->id];
    }

    public function test_saved_model_estimates_are_not_shown_to_farmers_while_accuracy_is_not_verified(): void
    {
        [$farmer, $schedule, $request] = $this->farmerWithSavedEstimates();

        foreach ($this->pages($request) as $page) {
            $html = $this->actingAs($farmer)->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString(self::SCHEDULE_YIELD, $html, $page.': saved schedule estimate shown');
            $this->assertStringNotContainsString(self::REQUEST_YIELD, $html, $page.': saved request estimate shown');
            $this->assertStringNotContainsString('7.90', $html, $page.': total derived from the estimate shown');
        }
        $this->actingAs($farmer)->get('/farmer/my-reports')->assertSee(__('farmer.home.harvest.unvalidated'));
        // The outlook badge is derived from the estimate (the page's help text still explains the levels).
        $this->assertStringNotContainsString('text-bg-success', $this->actingAs($farmer)->get('/analysis/yield')->getContent());

        // Display only: the stored history is untouched.
        $this->assertSame(self::SCHEDULE_YIELD, (string) $schedule->fresh()->predicted_yield);
        $this->assertSame(self::REQUEST_YIELD, (string) $request->fresh()->predicted_yield);
    }

    public function test_a_misconfigured_gate_value_still_withholds(): void
    {
        config(['prediction.rice_yield.gate' => 'not-a-mode']);
        [$farmer, , $request] = $this->farmerWithSavedEstimates();

        $this->actingAs($farmer)->get('/calendar')->assertOk()->assertDontSee(self::SCHEDULE_YIELD);
        $this->actingAs($farmer)->get('/farmer/my-reports')->assertOk()->assertDontSee(self::REQUEST_YIELD);
    }

    public function test_once_accuracy_is_verified_the_saved_estimates_return_with_their_label(): void
    {
        config(['prediction.rice_yield.accuracy_verified' => true]);
        [$farmer, , $request] = $this->farmerWithSavedEstimates();

        $this->actingAs($farmer)->get('/calendar')->assertOk()->assertSee(self::SCHEDULE_YIELD)->assertSee('Early estimate (not final)');
        $this->actingAs($farmer)->get('/farmer/my-reports')->assertOk()->assertSee(self::REQUEST_YIELD);
        $this->actingAs($farmer)->get('/analysis/yield')->assertOk()->assertSee(self::REQUEST_YIELD);
    }
}
