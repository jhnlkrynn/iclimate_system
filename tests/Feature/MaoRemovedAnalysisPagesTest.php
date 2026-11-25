<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaoRemovedAnalysisPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_restored_mao_analysis_pages_are_accessible_while_harvest_training_redirects(): void
    {
        $mao = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        foreach ([
            '/analysis/impact',
            '/analysis/forecast',
            '/analysis/model-evaluation',
        ] as $url) {
            $this->actingAs($mao)->get($url)->assertOk();
        }

        foreach ([
            '/analysis/harvest-training',
            '/analysis/harvest-training/export',
        ] as $url) {
            $this->actingAs($mao)->get($url)->assertRedirect(route('mao.dashboard'));
        }

        $forecastHtml = $this->actingAs($mao)->get(route('weather-predictions.index'))->assertOk()->getContent();
        $this->assertStringContainsString('/analysis/impact', $forecastHtml);
        $this->assertStringContainsString('/analysis/forecast', $forecastHtml);
        $this->assertStringContainsString('/analysis/model-evaluation', $forecastHtml);
        $this->assertStringNotContainsString('/analysis/harvest-training', $forecastHtml);
    }
}
