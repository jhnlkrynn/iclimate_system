<?php

namespace Tests\Feature;

use Tests\TestCase;

class HeatMapLegendConsistencyTest extends TestCase
{
    public function test_all_map_views_use_the_shared_heat_map_legend_component(): void
    {
        $heatmap = file_get_contents(resource_path('views/heatmap-areas/index.blade.php'));
        $mao = file_get_contents(resource_path('views/dashboards/mao.blade.php'));
        $component = file_get_contents(resource_path('views/components/heat-map-risk-legend.blade.php'));

        $this->assertStringContainsString('<x-heat-map-risk-legend include-farm-type />', $heatmap);
        $this->assertStringContainsString('<x-heat-map-risk-legend />', $mao);

        $this->assertStringContainsString('HeatMapLegend::legendRows()', $component);
        $this->assertStringContainsString('includeFarmType', $component);
    }
}
