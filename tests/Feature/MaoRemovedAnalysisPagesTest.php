<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaoRemovedAnalysisPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_mao_analysis_pages_redirect_to_mao_dashboard_and_are_absent_from_nav(): void
    {
        $mao = User::factory()->create([
            'role' => User::ROLE_MAO,
            'status' => User::STATUS_ACTIVE,
            'position' => 'Municipal Agriculturist',
        ]);

        foreach ([
            '/analysis/impact',
            '/analysis/impact/1',
            '/analysis/forecast',
            '/analysis/harvest-training',
            '/analysis/harvest-training/export',
            '/analysis/model-evaluation',
        ] as $url) {
            $this->actingAs($mao)->get($url)->assertRedirect(route('mao.dashboard'));
        }

        $forecastHtml = $this->actingAs($mao)->get(route('weather-predictions.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString('/analysis/impact', $forecastHtml);
        $this->assertStringNotContainsString('/analysis/forecast', $forecastHtml);
        $this->assertStringNotContainsString('/analysis/harvest-training', $forecastHtml);
        $this->assertStringNotContainsString('/analysis/model-evaluation', $forecastHtml);
    }
}
