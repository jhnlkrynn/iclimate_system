<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\User;
use App\Services\AI\RoleAssistantService;
use App\Services\Risk\BarangayRiskMapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClimoraPlaceholderRiskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.groq.enabled', false);
        $this->mock(BarangayRiskMapService::class, function ($mock): void {
            $mock->shouldReceive('detailFor')->andReturn(['risk_level' => 'High']);
        });
    }

    public function test_computed_rating_is_returned_to_climora(): void
    {
        HeatmapArea::query()->where('barangay', 'Malaruhatan')->update([
            'risk_level' => 'High', 'risk_type' => 'Heat', 'risk_score' => 0.9,
            'rainfall_status' => 'Low rainfall',
            'description' => 'Computed from live weather and production data.',
            'risk_computed_at' => now(),
        ]);

        $response = $this->ask('Is Malaruhatan high risk right now?');

        $response->assertOk()
            ->assertJsonPath('chat.prediction_result.status', 'current')
            ->assertJsonPath('chat.prediction_result.data.0.risk_level', 'High');
    }

    public function test_placeholder_moderate_low_and_high_ratings_are_ignored(): void
    {
        foreach (['Bagong Pook' => 'Moderate', 'Barangay 1 (Pob.)' => 'Low', 'Malaruhatan' => 'High'] as $barangay => $level) {
            $response = $this->ask("What is the climate risk in {$barangay}?");

            $response->assertOk();
            $this->assertSame('no_data', $response->json('chat.prediction_result.status'), $barangay.' '.$response->getContent());
            $this->assertStringNotContainsString($level.' risk', (string) $response->json('chat.answer'));
        }
    }

    public function test_no_rating_returns_unavailable_message_in_english_and_tagalog(): void
    {
        HeatmapArea::query()->where('barangay', 'Cumba')->delete();

        $english = $this->ask('What is the climate risk in Cumba?');
        $english->assertOk();
        $this->assertStringContainsString('No calculated farm-risk rating', $english->json('chat.answer'));

        $tagalog = $this->ask('Ano ang climate risk sa Cumba?');
        $tagalog->assertOk();
        $this->assertStringContainsString('Wala pang available na farm-risk rating', $tagalog->json('chat.answer'));
    }

    public function test_role_assistant_uses_computed_rating_but_ignores_placeholder(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_FARMER]);
        $service = app(RoleAssistantService::class);

        $placeholder = $service->barangayAnswer($user, 'What is the risk in Bagong Pook?', 'English');
        $this->assertStringNotContainsString('Climate risk: Moderate', $placeholder['answer']);
        $this->assertStringContainsString('No calculated farm-risk rating', $placeholder['answer']);

        HeatmapArea::query()->where('barangay', 'Malaruhatan')->update([
            'risk_level' => 'High', 'risk_type' => 'Heat', 'risk_score' => 0.9,
            'rainfall_status' => 'Low rainfall',
            'description' => 'Computed from live weather and production data.',
            'risk_computed_at' => now(),
        ]);
        $computed = $service->barangayAnswer($user, 'What is the risk in Malaruhatan?', 'English');
        $this->assertStringContainsString('Climate risk: High (Heat).', $computed['answer']);
    }

    private function ask(string $question)
    {
        return $this->actingAs(User::factory()->create(['role' => User::ROLE_FARMER]))
            ->postJson(route('ai-chat.message'), ['question' => $question]);
    }
}
