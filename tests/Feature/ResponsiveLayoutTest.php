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
        $maoOverview = file_get_contents(resource_path('views/dashboards/mao.blade.php'));
        $itDashboard = file_get_contents(resource_path('views/dashboards/it-expert.blade.php'));
        $crudIndex = file_get_contents(resource_path('views/crud/index.blade.php'));

        $this->assertStringContainsString('data-bs-target="#mobileSidebar"', $navigation);
        $this->assertStringContainsString('offcanvas offcanvas-start', $sidebar);
        $this->assertStringContainsString('overflow-auto', $sidebar);

        $this->assertStringContainsString('max-width: min(88vw, 340px)', $appCss);
        $this->assertStringContainsString('padding-bottom: max(5.25rem', $appCss);
        $this->assertStringContainsString('overscroll-behavior-inline: contain', $appCss);
        $this->assertStringContainsString('max-height: 100dvh', $appCss);

        // Farmer dashboard: its styles live in resources/css/farmer-dashboard.css; 2 KPI cards per row on phones, single column layout at 640px and below.
        $farmerCss = file_get_contents(resource_path('css/farmer-dashboard.css'));
        $this->assertStringContainsString('.mfd-kpis { display: grid; gap: var(--gap); grid-template-columns: repeat(2, minmax(0, 1fr)); }', $farmerCss);
        $this->assertStringContainsString('@media (max-width: 640px)', $farmerCss);
        // The MAO dashboard (2026-10): four tiles, two per row on phones (the Operations page and its stylesheet were removed).
        $this->assertStringContainsString('@media (min-width: 560px) { .mhd-tiles { grid-template-columns: repeat(2, minmax(0, 1fr)); } }', file_get_contents(resource_path('css/mao-dashboard.css')));
        // "Farmers by Organization" chart moved from the MAO dashboard to the Farmer Profiles page.
        $this->assertStringContainsString('.farmer-org-legend { grid-template-columns: 1fr; }', $crudIndex);
        // IT dashboard: five farmer-style KPI cards in its own grid (.itd-kpis: 2 per row on phones, 3 then 2 on tablets, 5 on desktops);
        // its own styles live in resources/css/it-dashboard.css. Wide tables scroll inside their own box, never the page.
        $itCss = file_get_contents(resource_path('css/it-dashboard.css'));
        $this->assertStringContainsString('class="itd-kpis"', file_get_contents(resource_path('views/dashboards/it/kpis.blade.php')));
        $this->assertStringContainsString('.itd-kpis { display: grid; gap: 12px; grid-template-columns: repeat(2, minmax(0, 1fr)); }', $itCss);
        $this->assertStringContainsString('@media (min-width: 1200px) {' . "\n" . '    .itd-kpis { grid-template-columns: repeat(5, minmax(0, 1fr)); }', str_replace("\r\n", "\n", $itCss));
        $this->assertStringContainsString('.itd-table-wrap { max-width: 100%; overflow-x: auto;', $itCss);
        $this->assertStringContainsString('@media (max-width: 640px)', $itCss);
        $this->assertStringNotContainsString('<style>', $itDashboard);
        // MAO overview: built on the farmer dashboard system; its styles live in resources/css/mao-dashboard.css.
        // 1 KPI per row on phones, 2 on small tablets, 3 on tablets, up to 5 on desktop; tables become cards on phones.
        $maoCss = file_get_contents(resource_path('css/mao-dashboard.css'));
        $this->assertStringNotContainsString('<style>', $maoOverview);
        $this->assertStringContainsString('.mtd-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); }', $maoCss);
        $this->assertStringContainsString('.mtd-kpis { grid-template-columns: repeat(3, minmax(0, 1fr)); }', $maoCss);
        $this->assertStringContainsString('repeat(var(--mtd-kpi-count, 5), minmax(0, 1fr))', $maoCss);
        $this->assertStringContainsString('.mtd-table-cards thead { display: none; }', $maoCss);
    }

    public function test_rendered_blade_sources_do_not_contain_merge_conflict_markers(): void
    {
        $paths = [
            resource_path('views/components/ai-chat-widget.blade.php'),
            resource_path('views/dashboards/farmer.blade.php'),
            resource_path('views/dashboards/it-expert.blade.php'),
        ];

        foreach ($paths as $path) {
            $content = file_get_contents($path);

            $this->assertDoesNotMatchRegularExpression('/^(<<<<<<<|=======|>>>>>>>) /m', $content, $path);
        }
    }

    public function test_shared_sidebar_docks_controls_and_leaves_scrolling_to_the_content_column(): void
    {
        $sidebarCss = file_get_contents(resource_path('css/sidebar.css'));

        $this->assertStringContainsString('height: 100dvh;', $sidebarCss);
        $this->assertStringContainsString('overflow-y: hidden;', $sidebarCss);
        // Since 2026-10-08 the links share the menu's height (52px, 30px up to --sb-tab-max) instead of the menu scrolling; one rule for every role.
        $this->assertStringContainsString('flex: 1 1 52px;', $sidebarCss);
        $this->assertStringContainsString('max-height: var(--sb-tab-max);', $sidebarCss);
        $this->assertStringNotContainsString('justify-content: space-evenly', $sidebarCss);
        // Scrolling inside the sidebar is a last resort for windows under 480px high only.
        $this->assertMatchesRegularExpression('/max-height: 480px\) \{\s*html body \.ic-sidebar\.sidebar-fixed \.ic-sb-nav \{ overflow-y: auto; \}/', $sidebarCss);
        $this->assertStringContainsString('html body .app-main {', $sidebarCss);
        $this->assertStringContainsString('overflow-y: auto;', $sidebarCss);
        $this->assertStringContainsString('html body #mobileSidebar .ic-sb-nav', $sidebarCss);
        $this->assertStringContainsString('data-theme-set="light"', file_get_contents(resource_path('views/layouts/sidebar.blade.php')));
        $this->assertStringContainsString('data-theme-set="dark"', file_get_contents(resource_path('views/layouts/sidebar.blade.php')));
    }
}
