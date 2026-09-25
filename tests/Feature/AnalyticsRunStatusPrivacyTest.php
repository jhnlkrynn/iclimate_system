<?php

namespace Tests\Feature;

use App\Models\AnalyticsRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AnalyticsRunStatusPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private function completedRun(User $user): AnalyticsRun
    {
        return AnalyticsRun::query()->create([
            'type' => 'rice_yield_prediction',
            'status' => AnalyticsRun::STATUS_COMPLETED,
            'requested_by' => $user->id,
            'input' => [],
            'result' => [
                'ml_result' => [
                    'predicted_yield' => 4.2,
                    'source' => 'iClimate Python model',
                    'model_input' => ['rainfall' => 1],
                    'model_path' => 'storage/models/rice_yield_model_final.pkl',
                ],
                'weather_result' => ['source_name' => 'iClimate monthly Random Forest model'],
            ],
            'completed_at' => now(),
        ]);
    }

    public static function nonTechnicalRoles(): array
    {
        return [[User::ROLE_FARMER], [User::ROLE_MAO]];
    }

    #[DataProvider('nonTechnicalRoles')]
    public function test_farmer_and_mao_do_not_receive_raw_model_result_from_status_endpoint(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $run = $this->completedRun($user);

        $response = $this->actingAs($user)->getJson(route('analytics-runs.show', $run))->assertOk();
        $body = $response->getContent();

        $response->assertJsonPath('status', AnalyticsRun::STATUS_COMPLETED)->assertJsonPath('result', null);
        foreach (['.pkl', 'Random Forest', 'Python', 'model_input', 'storage/models'] as $internal) {
            $this->assertStringNotContainsString($internal, $body);
        }
    }

    public function test_it_expert_still_receives_the_raw_result_for_troubleshooting(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_IT_EXPERT]);
        $run = $this->completedRun($user);

        $this->actingAs($user)->getJson(route('analytics-runs.show', $run))
            ->assertOk()
            ->assertJsonPath('result.ml_result.predicted_yield', 4.2);
    }
}
