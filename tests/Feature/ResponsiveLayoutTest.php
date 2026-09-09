<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResponsiveLayoutTest extends TestCase
{
    public function test_shared_mobile_layout_contract_is_present(): void
    {
        $appCss = file_get_contents(resource_path('css/app.css'));
        $navigation = file_get_contents(resource_path('views/layouts/navigation.blade.php'));
        $sidebar = file_get_contents(resource_path('views/layouts/sidebar.blade.php'));
        $maoDashboard = file_get_contents(resource_path('views/dashboards/mao.blade.php'));
        $farmerDashboard = file_get_contents(resource_path('views/dashboards/farmer.blade.php'));
        $itDashboard = file_get_contents(resource_path('views/dashboards/it-expert.blade.php'));
        $heatmap = file_get_contents(resource_path('views/heatmap-areas/index.blade.php'));

        $this->assertStringContainsString('data-bs-target="#mobileSidebar"', $navigation);
        $this->assertStringContainsString('offcanvas offcanvas-start', $sidebar);
        $this->assertStringContainsString('overflow-auto', $sidebar);

        $this->assertStringContainsString('max-width: min(88vw, 340px)', $appCss);
        $this->assertStringContainsString('padding-bottom: max(5.25rem', $appCss);
        $this->assertStringContainsString('overscroll-behavior-inline: contain', $appCss);
        $this->assertStringContainsString('max-height: 100dvh', $appCss);

        $this->assertStringContainsString('.farmer-hero-summary { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $farmerDashboard);
        $this->assertStringContainsString('.climate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $farmerDashboard);
        $this->assertStringContainsString('.mao-stat-grid, .live-weather-grid, .risk-level-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $maoDashboard);
        $this->assertStringContainsString('.farmer-org-legend { grid-template-columns: 1fr; max-height: 230px; overflow-y: auto;', $maoDashboard);
        $this->assertStringContainsString('.it-stat-grid, .farmer-org-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $itDashboard);
        $this->assertStringContainsString('height: min(52vh, 320px)', $maoDashboard);
        $this->assertStringContainsString('#barangayRiskMap { height: min(58vh, 360px); min-height: 280px; }', $heatmap);
        $this->assertStringContainsString('<details class="ic-collapse"', $heatmap);
    }

    public function test_rendered_blade_sources_do_not_contain_merge_conflict_markers(): void
    {
        $paths = [
            resource_path('views/components/ai-chat-widget.blade.php'),
            resource_path('views/dashboards/farmer.blade.php'),
            resource_path('views/dashboards/it-expert.blade.php'),
            resource_path('views/heatmap-areas/index.blade.php'),
        ];

        foreach ($paths as $path) {
            $content = file_get_contents($path);

            $this->assertDoesNotMatchRegularExpression('/^(<<<<<<<|=======|>>>>>>>) /m', $content, $path);
        }
    }
}
