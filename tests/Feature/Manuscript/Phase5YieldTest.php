<?php

namespace Tests\Feature\Manuscript;

use App\Models\BarangayProfile;
use App\Models\User;
use App\Models\YieldPredictionRequest;
use App\Services\Prediction\RiceYieldPredictionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/** Manuscript – Naicel (2026-10-08), Phase 5: farmer Yield Prediction and MAO Yield Forecast (model mocked). */
class Phase5YieldTest extends TestCase
{
    use RefreshDatabase;

    private function modelReturns(?float $yield, array $missing = []): void
    {
        $this->mock(RiceYieldPredictionService::class, function ($mock) use ($yield, $missing) {
            $mock->shouldReceive('predictForUser')->andReturn($yield === null
                ? ['success' => false, 'missing_inputs' => $missing]
                : ['success' => true, 'yield_tons_per_hectare' => $yield, 'predicted_yield' => $yield, 'uncertainty' => 0.4]);
        });
    }

    public function test_a_farmer_requests_a_prediction_and_gets_tons_usual_yield_and_outlook(): void
    {
        $this->modelReturns(9.5);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'barangay' => 'Balibago']);

        $this->actingAs($farmer)->get(route('analysis.yield'))->assertOk()->assertSee('data-manuscript-tabs="analysis"', false);
        $this->actingAs($farmer)->post(route('analysis.yield.store'), [
            'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Balibago', 'area_hectares' => '2',
        ])->assertRedirect(route('analysis.yield'));

        $request = YieldPredictionRequest::query()->sole();
        $this->assertSame(YieldPredictionRequest::DONE, $request->status);
        $this->assertEquals(9.5, (float) $request->predicted_yield);
        $this->assertEquals(19.0, (float) $request->total_tons);
        $this->assertNotNull($request->historical_average);
        $this->assertSame('Favorable', $request->impact_level);   // far above Lian's usual September yield
        $this->assertSame(9, $request->target_month->month);

        $this->actingAs($farmer)->get(route('analysis.yield'))->assertSee('19 t')->assertSee('Favorable');
    }

    public function test_a_prediction_the_model_cannot_make_says_why(): void
    {
        $this->modelReturns(null, ['rainfall']);
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER, 'preferred_language' => 'fil']);

        $this->actingAs($farmer)->post(route('analysis.yield.store'), ['season' => 'Dry', 'farm_type' => 'Rainfed', 'barangay' => 'Cumba', 'area_hectares' => '1']);

        $request = YieldPredictionRequest::query()->sole();
        $this->assertSame(YieldPredictionRequest::FAILED, $request->status);
        $this->assertStringContainsString('rainfall', $request->error);
        $this->actingAs($farmer)->get(route('analysis.yield'))->assertSee('Taya ng Ani')->assertSee('Hindi available');
    }

    public function test_mao_runs_a_municipal_forecast_using_the_barangay_rice_areas(): void
    {
        $this->modelReturns(4.0);
        BarangayProfile::query()->where('barangay', 'Balibago')->update(['farm_area_hectares' => 100]);
        BarangayProfile::query()->where('barangay', 'Cumba')->update(['farm_area_hectares' => 50]);
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('analysis.forecast.store'), ['scope' => 'municipal', 'season' => 'Wet', 'farm_type' => 'Irrigated'])->assertRedirect();

        $forecast = YieldPredictionRequest::query()->sole();
        $this->assertSame('municipal', $forecast->scope);
        $this->assertEquals(150, (float) $forecast->area_hectares);
        $this->assertEquals(600, (float) $forecast->total_tons);
        $this->actingAs($mao)->get(route('analysis.forecast'))->assertOk()->assertSee('All of Lian')->assertSee('600 t')->assertSee('data-predicted-vs-actual', false);
    }

    public function test_a_barangay_forecast_needs_a_barangay(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->post(route('analysis.forecast.store'), ['scope' => 'barangay', 'season' => 'Wet', 'farm_type' => 'Irrigated'])->assertSessionHasErrors('barangay');
    }

    public function test_target_month_is_the_next_harvest_month_of_the_season(): void
    {
        $october = CarbonImmutable::create(2026, 10, 1);
        $this->assertSame('2027-09', YieldPredictionRequest::targetMonthFor('Wet', $october)->format('Y-m'));
        $this->assertSame('2027-03', YieldPredictionRequest::targetMonthFor('Dry', $october)->format('Y-m'));
        $this->assertSame('2026-09', YieldPredictionRequest::targetMonthFor('Wet', CarbonImmutable::create(2026, 5, 1))->format('Y-m'));
    }

    public function test_access_follows_the_manuscript_matrix(): void
    {
        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        $this->actingAs($farmer)->get(route('analysis.forecast'))->assertStatus(403);
        $this->actingAs($it)->get(route('analysis.yield'))->assertStatus(403);
        $this->actingAs($it)->post(route('analysis.forecast.store'), [])->assertStatus(403);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
