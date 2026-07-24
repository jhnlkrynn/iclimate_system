<?php

namespace Tests\Feature;

use App\Models\HeatmapArea;
use App\Models\PlantingAdvisory;
use App\Models\TyphoonSafetyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TyphoonSafetyResponseValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_farmer_cannot_respond_when_only_heatmap_has_typhoon_risk(): void
    {
        $farmer = User::factory()->farmer()->create();
        HeatmapArea::factory()->create([
            'risk_type' => 'Typhoon',
            'risk_level' => 'Severe',
            'risk_score' => 0.95,
        ]);

        $this->actingAs($farmer)
            ->from(route('planting-advisories.index'))
            ->post(route('typhoon-safety.store'), [
                'event_key' => 'heatmap-typhoon-'.now()->format('Ymd'),
                'status' => TyphoonSafetyResponse::STATUS_SAFE,
            ])
            ->assertRedirect(route('planting-advisories.index'))
            ->assertSessionHas('error', 'No active typhoon safety check is available right now.');

        $this->assertDatabaseCount('typhoon_safety_responses', 0);
    }

    public function test_farmer_cannot_respond_before_typhoon_advisory_starts(): void
    {
        $farmer = User::factory()->farmer()->create();
        $advisory = $this->typhoonAdvisory([
            'valid_from' => now()->addHour(),
            'valid_until' => now()->addDay(),
        ]);

        $this->actingAs($farmer)
            ->from(route('planting-advisories.index'))
            ->post(route('typhoon-safety.store'), [
                'event_key' => 'advisory-'.$advisory->id,
                'status' => TyphoonSafetyResponse::STATUS_SAFE,
            ])
            ->assertRedirect(route('planting-advisories.index'))
            ->assertSessionHas('error', 'No active typhoon safety check is available right now.');

        $this->assertDatabaseCount('typhoon_safety_responses', 0);
    }

    public function test_farmer_cannot_respond_to_rainfall_advisory_with_pagasa_navigation_text(): void
    {
        $farmer = User::factory()->farmer()->create();
        $advisory = PlantingAdvisory::factory()->create([
            'title' => 'PAGASA Batangas Rainfall Advisory',
            'summary' => 'Official PAGASA online advisory detected for Lian/Batangas. Details from PAGASA weather outlook.',
            'message' => 'PAGASA navigation includes Tropical Cyclone Bulletin, Storm Surge, and other menu links.',
            'content' => 'PAGASA navigation includes Tropical Cyclone Bulletin, Storm Surge, and other menu links.',
            'source' => 'PAGASA',
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
        ]);

        $this->actingAs($farmer)
            ->from(route('planting-advisories.index'))
            ->post(route('typhoon-safety.store'), [
                'event_key' => 'advisory-'.$advisory->id,
                'status' => TyphoonSafetyResponse::STATUS_SAFE,
            ])
            ->assertRedirect(route('planting-advisories.index'))
            ->assertSessionHas('error', 'No active typhoon safety check is available right now.');

        $this->assertDatabaseCount('typhoon_safety_responses', 0);
    }

    public function test_farmer_can_respond_during_active_typhoon_advisory(): void
    {
        $farmer = User::factory()->farmer()->create();
        $advisory = $this->typhoonAdvisory();

        $this->actingAs($farmer)
            ->from(route('planting-advisories.index'))
            ->post(route('typhoon-safety.store'), [
                'event_key' => 'advisory-'.$advisory->id,
                'status' => TyphoonSafetyResponse::STATUS_NEEDS_HELP,
                'note' => 'Need assistance near the field.',
            ])
            ->assertRedirect(route('planting-advisories.index'))
            ->assertSessionHas('success', 'Help request sent. MAO can now see that you may need assistance.');

        $this->assertDatabaseHas('typhoon_safety_responses', [
            'user_id' => $farmer->id,
            'event_key' => 'advisory-'.$advisory->id,
            'event_title' => 'Typhoon safety advisory',
            'status' => TyphoonSafetyResponse::STATUS_NEEDS_HELP,
            'note' => 'Need assistance near the field.',
        ]);
    }

    private function typhoonAdvisory(array $overrides = []): PlantingAdvisory
    {
        return PlantingAdvisory::factory()->create($overrides + [
            'title' => 'Typhoon safety advisory',
            'summary' => 'Typhoon conditions are affecting Lian.',
            'message' => 'Please confirm your safety during the typhoon.',
            'content' => 'Please confirm your safety during the typhoon.',
            'source' => 'PAGASA',
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => now()->subHour(),
            'valid_until' => now()->addDay(),
        ]);
    }
}
