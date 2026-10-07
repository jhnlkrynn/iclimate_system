<?php

namespace Tests\Feature;

use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Support\YieldCorrections;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Survey Results (2026-10): the MAO Dashboard shows farmers' forecast answers per month and predicted against actual
 * yield; IT's Model Evaluation lists the same yield corrections as data for future retraining. Saved records only.
 */
class SurveyResultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->travelTo(CarbonImmutable::parse('2026-10-06 10:00:00', 'Asia/Manila'));
        Http::fake(['*' => Http::response(['error' => 'down'], 500)]);
    }

    private function harvest(float $predicted, float $actual): void
    {
        FarmerPlantingSchedule::query()->create(['user_id' => User::factory()->farmer()->create()->id, 'planting_date' => '2026-05-10',
            'scheduled_harvest_date' => '2026-08-28', 'harvested_on' => '2026-09-01', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza',
            'farm_area_hectares' => 1, 'predicted_yield' => $predicted, 'actual_yield' => $actual]);
    }

    public function test_yield_corrections_compare_predicted_with_actual_yield(): void
    {
        $this->assertSame(0, YieldCorrections::summary()['count']);

        $this->harvest(4.0, 3.5);
        $this->harvest(4.0, 5.0);
        // A plan with no harvest yet is not a correction.
        FarmerPlantingSchedule::query()->create(['user_id' => User::factory()->farmer()->create()->id, 'planting_date' => '2026-06-10',
            'scheduled_harvest_date' => '2026-09-28', 'season' => 'Wet', 'farm_type' => 'Irrigated', 'barangay' => 'Prenza', 'farm_area_hectares' => 1, 'predicted_yield' => 4.2]);

        $summary = YieldCorrections::summary();
        $this->assertSame(2, $summary['count']);
        $this->assertSame(4.0, $summary['predicted']);
        $this->assertSame(4.25, $summary['actual']);
        $this->assertSame(0.75, $summary['error']);
    }

    public function test_mao_dashboard_shows_survey_results(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]);
        // No answers yet: the Survey Results cards are left out (2026-10-07: no empty sections on the MAO dashboard).
        $this->actingAs($mao)->get(route('mao.dashboard'))->assertOk()
            ->assertDontSee('Survey Results: Forecast Accuracy')->assertDontSee('No farmer has answered the forecast survey yet.');

        Cache::flush();
        $answer = fn (string $accuracy, ?string $observed = null) => WeatherForecastFeedback::factory()->accuracy($accuracy, $observed)
            ->create(['user_id' => User::factory()->farmer()->create(['barangay' => 'Malaruhatan'])->id]);
        $answer(WeatherForecastFeedback::ACCURATE);
        $answer(WeatherForecastFeedback::ACCURATE);
        $answer(WeatherForecastFeedback::PARTLY, 'Light Rain');
        $answer(WeatherForecastFeedback::DIFFERENT, 'Sunny');
        $this->harvest(4.0, 3.5);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_MAO, 'status' => User::STATUS_ACTIVE]))->get(route('mao.dashboard'))->assertOk()
            ->assertSeeInOrder(['Survey Results: Forecast Accuracy', 'August 2026', '50%', '25%', '25%'])
            ->assertSeeInOrder(['Survey Results: Predicted vs Actual Yield', 'Predicted 4.00 t/ha', 'Actual 3.50 t/ha', '1 harvest', 'Prenza', '-0.50']);
    }

    public function test_model_evaluation_lists_the_corrections_as_data_for_future_retraining(): void
    {
        $it = User::factory()->create(['role' => User::ROLE_IT_EXPERT, 'status' => User::STATUS_ACTIVE]);
        $this->actingAs($it)->get(route('model-evaluation.index'))->assertOk()
            ->assertSee('Data for Future Retraining')->assertSee('The model is not retrained automatically.')->assertSee('0 <span', false);

        $this->harvest(4.0, 5.0);

        $this->actingAs($it)->get(route('model-evaluation.index'))->assertOk()
            ->assertSeeInOrder(['Data for Future Retraining', '1 <span', 'correction on record', 'average miss 1.00 t/ha', 'Prenza', '4.00', '5.00', '+1.00'], false);
    }
}
