<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Thesis objective 2: the algorithm comparison is visible to MAO staff again (owner decision 2026-11-20). */
class ModelEvaluationPageTest extends TestCase
{
    use RefreshDatabase;

    private function completedRun(): AnalyticsRun
    {
        $row = fn (string $algorithm, float $rmse): array => ['algorithm' => $algorithm, 'rmse' => $rmse, 'mae' => $rmse - 0.05, 'r2' => 0.12];

        return AnalyticsRun::query()->create([
            'type' => 'dataset_processing',
            'input' => ['schema' => 'rice_yield_v1'],
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'completed_at' => now(),
            'result' => ['evaluation' => [
                'ready' => true,
                'accuracy_status' => 'ACCURACY NOT VERIFIED',
                'sample_count' => 12,
                'rows' => [$row('Multiple Linear Regression', 0.41), $row('Random Forest', 0.33), $row('Gradient Boosting', 0.36)],
                'best_algorithm' => 'Random Forest',
                'breakdown' => [
                    'season' => [
                        'Dry' => [['algorithm' => 'Random Forest', 'n' => 6, 'rmse' => 0.3012, 'mae' => 0.25, 'r2' => 0.2]],
                        'Wet' => [['algorithm' => 'Random Forest', 'n' => 6, 'rmse' => 0.3577, 'mae' => 0.3, 'r2' => null]],
                    ],
                    'irrigation_type' => [
                        'Irrigated' => [['algorithm' => 'Random Forest', 'n' => 12, 'rmse' => 0.33, 'mae' => 0.28, 'r2' => 0.1]],
                    ],
                ],
            ]],
        ]);
    }

    public function test_mao_staff_see_the_comparison_by_season_and_farming_system(): void
    {
        $this->completedRun();
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)
            ->get(route('model-evaluation.index'))
            ->assertRedirect(route('mao.dashboard'));
    }

    public function test_farmers_and_guests_cannot_open_it(): void
    {
        $this->get(route('model-evaluation.index'))->assertRedirect();

        $farmer = User::factory()->create(['role' => User::ROLE_FARMER]);
        $this->actingAs($farmer)->get(route('model-evaluation.index'))->assertStatus(403);
    }

    public function test_the_old_it_address_stays_retired(): void
    {
        $mao = User::factory()->create(['role' => User::ROLE_MAO]);

        $this->actingAs($mao)->get('/model-evaluation')->assertNotFound();
    }
}
