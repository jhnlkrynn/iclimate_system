<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\YieldPredictionRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreMigrationGuardsPhase2Test extends TestCase
{
    use RefreshDatabase;

    public function test_save_override_returns_friendly_error_when_override_table_is_missing(): void
    {
        $mao = User::factory()->maoPersonnel()->create();
        Schema::dropIfExists('farm_type_yield_overrides');

        $response = $this->actingAs($mao)
            ->from(route('mao.associations.farm-types.index'))
            ->post(route('mao.associations.yield-override.store'), [
                'rainfed_diff_percent' => -15,
                'note' => 'MAO field survey',
            ]);

        $response->assertRedirect(route('mao.associations.farm-types.index'));
        $response->assertSessionHasErrors('override');
    }

    public function test_yield_useful_returns_friendly_error_when_useful_column_is_missing(): void
    {
        $farmer = User::factory()->farmer()->create();
        $prediction = YieldPredictionRequest::query()->create([
            'user_id' => $farmer->id,
            'scope' => YieldPredictionRequest::SCOPE_FARM,
            'barangay' => 'Prenza',
            'season' => 'Wet',
            'farm_type' => 'Irrigated',
            'area_hectares' => 1.5,
            'target_month' => now()->toDateString(),
            'status' => YieldPredictionRequest::DONE,
        ]);

        Schema::table('yield_prediction_requests', function ($table): void {
            $table->dropColumn('useful');
        });

        $this->actingAs($farmer)
            ->postJson(route('analysis.yield.useful', $prediction), ['useful' => true])
            ->assertStatus(503)
            ->assertJsonStructure(['message']);
    }
}
