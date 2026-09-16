<?php

namespace Tests\Feature;

use App\Jobs\RefreshHeatmapRisk;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\RiceProduction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HeatmapPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
    }

    public function test_heatmap_api_returns_lightweight_accurate_data_then_hits_layer_cache(): void
    {
        HeatmapArea::query()->where('barangay', 'Matabungkay')->firstOrFail()->update([
            'risk_level' => 'Severe',
            'risk_score' => 0.91,
            'risk_type' => 'Flood',
            'description' => 'Flood exposure is the primary computed reason.',
            'irrigation_recommendation' => 'Open drainage channels immediately.',
        ]);

        $first = $this->getJson('/api/heatmaps/lian-barangays?layer=impact')
            ->assertOk()
            ->assertHeader('X-Heatmap-Cache', 'MISS')
            ->assertJsonFragment([
                'barangay' => 'Matabungkay',
                'risk_level' => 'Severe',
                'color' => '#ef4444',
            ])
            ->assertJsonStructure([
                'layer', 'generated_at',
                'areas' => [[
                    'barangay', 'polygon_id', 'coordinates', 'risk_score', 'risk_level', 'risk_type',
                    'color', 'reason', 'suggested_action', 'updated_at',
                ]],
            ]);

        $matabungkay = collect($first->json('areas'))->firstWhere('barangay', 'Matabungkay');
        $this->assertSame(
            ['barangay', 'polygon_id', 'coordinates', 'risk_score', 'risk_level', 'risk_type', 'color', 'reason', 'suggested_action', 'updated_at'],
            array_keys($matabungkay),
        );

        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')
            ->assertOk()
            ->assertHeader('X-Heatmap-Cache', 'HIT');
    }

    public function test_each_layer_has_its_own_cache_and_source_changes_invalidate_it(): void
    {
        $area = HeatmapArea::query()->where('barangay', 'Matabungkay')->firstOrFail();

        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')->assertHeader('X-Heatmap-Cache', 'MISS');
        $this->getJson('/api/heatmaps/lian-barangays?layer=rainfall')->assertHeader('X-Heatmap-Cache', 'MISS');
        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')->assertHeader('X-Heatmap-Cache', 'HIT');

        ClimateRecord::factory()->create();
        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')->assertHeader('X-Heatmap-Cache', 'MISS');

        RiceProduction::factory()->create(['barangay' => 'Matabungkay']);
        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')->assertHeader('X-Heatmap-Cache', 'MISS');

        FarmerProfile::factory()->create(['barangay' => 'Matabungkay']);
        $this->getJson('/api/heatmaps/lian-barangays?layer=impact')->assertHeader('X-Heatmap-Cache', 'MISS');

        $area->update(['risk_score' => 0.77]);
        $response = $this->getJson('/api/heatmaps/lian-barangays?layer=impact')
            ->assertHeader('X-Heatmap-Cache', 'MISS');
        $this->assertSame(0.77, collect($response->json('areas'))->firstWhere('barangay', 'Matabungkay')['risk_score']);
    }

    public function test_page_exposes_last_known_map_and_initializes_only_when_visible(): void
    {
        Queue::fake();
        $user = User::factory()->create(['role' => User::ROLE_MAO]);
        $this->actingAs($user)
            ->get(route('heatmap-areas.index'))
            ->assertOk()
            ->assertSee('Showing latest saved heat map while refreshing current data.')
            ->assertSee('IntersectionObserver', false)
            ->assertSee('L.map(mapEl', false)
            ->assertSee('loadLayer(activeLayer, true)', false);

        Queue::assertPushed(RefreshHeatmapRisk::class);
    }
}
