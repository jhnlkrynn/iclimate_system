<?php

namespace Tests\Feature;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelEvaluationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_expert_can_view_model_evaluation_report(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);

        foreach (range(2020, 2025) as $year) {
            foreach ([ClimateRecord::SEASON_DRY, ClimateRecord::SEASON_WET] as $season) {
                ClimateRecord::query()->create([
                    'record_date' => $year.($season === ClimateRecord::SEASON_DRY ? '-02-15' : '-07-15'),
                    'rainfall' => $season === ClimateRecord::SEASON_DRY ? 90 + ($year - 2020) : 220 + (($year - 2020) * 12),
                    'temperature' => 28 + (($year - 2020) * 0.2),
                    'humidity' => $season === ClimateRecord::SEASON_DRY ? 70 : 84,
                    'wind_speed' => 8 + ($year - 2020),
                    'season' => $season,
                    'source' => 'PAGASA',
                ]);

                RiceProduction::query()->create([
                    'barangay' => 'Matabungkay',
                    'season' => $season,
                    'irrigation_type' => $season === ClimateRecord::SEASON_DRY ? 'Irrigated' : 'Rainfed',
                    'yield_per_hectare' => $season === ClimateRecord::SEASON_DRY ? 4.1 : 3.5,
                    'area_hectares' => 120,
                    'total_production' => 420,
                    'year' => $year,
                ]);
            }
        }

        $this->actingAs($user)
            ->get(route('model-evaluation.index'))
            ->assertOk()
            ->assertSee('Model Evaluation Report')
            ->assertSee('Multiple Linear Regression')
            ->assertSee('Random Forest')
            ->assertSee('Gradient Boosting')
            ->assertSee('RMSE')
            ->assertSee('MAE')
            ->assertSee('R²')
            ->assertSee('0.802549')
            ->assertSee('0.591967')
            ->assertSee('0.240319')
            ->assertSee('iClimate_ML_Model_Training.ipynb')
            ->assertSee('Model Details')
            ->assertSee('Ensemble regression model')
            ->assertSee('Random Forest is the best model here')
            ->assertSee('Side-by-Side Model Comparison')
            ->assertSee('Two models')
            ->assertSee('Three models')
            ->assertSee('Model 1')
            ->assertSee('Model 2')
            ->assertSee('Model 3')
            ->assertSee('Simple guide')
            ->assertSee('Lower RMSE and MAE means fewer prediction mistakes')
            ->assertSee('me-model-logo')
            ->assertSee('Selected as the deployed rice yield model')
            ->assertSee('Selected Model');
    }

    public function test_mao_cannot_view_model_evaluation_report(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($user)
            ->get(route('model-evaluation.index'))
            ->assertForbidden();
    }

    public function test_farmer_cannot_view_model_evaluation_report(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);

        $this->actingAs($user)
            ->get(route('model-evaluation.index'))
            ->assertForbidden();
    }
}
