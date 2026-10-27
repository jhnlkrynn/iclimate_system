@php
    $user = auth()->user();
    $isFarmer = $user->role === \App\Models\User::ROLE_FARMER;
    $isMao = $user->role === \App\Models\User::ROLE_MAO;
    $isIt = $user->role === \App\Models\User::ROLE_IT_EXPERT;
    $dashboardRoute = $user->dashboardRoute();

    $sidebarIcon = function (string $key): string {
        return match ($key) {
            'dashboard' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M2.5 8.5 9 3l6.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 7.5V15h10V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'heatmap' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M3 3.5h12v11H3z" stroke="currentColor" stroke-width="1.4"/><path d="M5.5 12V9.5M9 12V6M12.5 12V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
            'advisories' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 2.5 16 15H2L9 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 8v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="9" cy="13" r=".9" fill="currentColor"/></svg>',
            'calendar' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2.5" y="3.5" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 7.5h13M6 2v3M12 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            'community' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="6.5" cy="6.5" r="2.2" stroke="currentColor" stroke-width="1.4"/><circle cx="12.5" cy="7.5" r="1.8" stroke="currentColor" stroke-width="1.4"/><path d="M2 15c.5-2.7 2-4.2 4.5-4.2s4 1.5 4.5 4.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M11 15c.4-2.2 1.6-3.4 3.2-3.4s2.8 1 3.2 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'messages' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m2.5 5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'notifications' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5 7.2C5 4.6 6.8 3 9 3s4 1.6 4 4.2c0 3.6 1.3 4.6 1.3 4.6H3.7S5 10.8 5 7.2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.3 14.5a1.8 1.8 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'climate-records' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5 2.5h5.5L14 6v9.5H5V2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 8h4M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'weather' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 13.5v1M9 13.5v1.6M12 13.5v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'rice-production' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 2c1.4 2 2.5 3.6 2.5 5.3A2.5 2.5 0 1 1 6.5 7.3C6.5 5.6 7.6 4 9 2Z" stroke="currentColor" stroke-width="1.4"/><path d="M9 9.8V16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M9 12.5c-1.6 0-2.8.9-3.2 2.5M9 12.5c1.6 0 2.8.9 3.2 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>',
            'farmer-profiles' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'reports' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="3.5" y="2.5" width="11" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 6.5h5M6.5 9.5h5M6.5 12.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'user-management' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6.5" r="2.3" stroke="currentColor" stroke-width="1.4"/><path d="M2.3 15c.6-3 2.3-4.6 4.7-4.6s4.1 1.6 4.7 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12 4.5a2.2 2.2 0 0 1 0 4.3M14.2 10.6c1.5.5 2.4 1.9 2.8 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'system-health' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M2 9h3l1.6-4 3 8 1.9-4H16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'system-logs' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="4" y="2.5" width="10" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M7 2.5v-.2a1.2 1.2 0 0 1 1.2-1h1.6a1.2 1.2 0 0 1 1.2 1v.2" stroke="currentColor" stroke-width="1.3"/><path d="M6.5 8h5M6.5 11h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            // Rice Varieties: a seedling with a grain head.
            'seedling' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 16V9" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M9 9c0-3 2.2-5.2 5.5-5.2C14.5 7 12.3 9 9 9Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M9 11c0-2.6-1.9-4.6-4.8-4.6C4.2 9.2 6.1 11 9 11Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M5.5 16h7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            'ai' => '<svg width="16" height="16" viewBox="0 0 20 20" fill="none"><rect x="4" y="7" width="12" height="9" rx="3" stroke="currentColor" stroke-width="1.5"/><circle cx="7.8" cy="11.5" r="1" fill="currentColor"/><circle cx="12.2" cy="11.5" r="1" fill="currentColor"/><path d="M10 7V4M7.5 4.5l1-1.2M12.5 4.5l-1-1.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            default => '',
        };
    };

    // Second element: every route that keeps the item highlighted. A page that has no menu item of its own (it is
    // opened from a button on another page) is listed under the item it is opened from, so one item is always
    // highlighted and people can see where they are.
    $routeDefs = [
        'dashboard' => [$dashboardRoute, [$dashboardRoute, 'farmer.dashboard.*', 'farmer.survey.*', 'farmer.boundary.*', 'mao.dashboard.*', 'mao.operations', 'admin.dashboard.*']],
        'field-reports' => ['field-reports.index', ['field-reports.*']],
        // MAO's "Records": Climate Records and Rice Production are two tabs of one item.
        'records' => ['climate-records.index', ['climate-records.*', 'rice-productions.*']],
        'heatmap' => ['heat-map.index', ['heat-map.*']],
        'advisories' => ['planting-advisories.index', ['planting-advisories.*', 'management.advisories.*', 'mao.alerts.*']],   // Manuscript – Naicel: + Send Alert
        'calendar' => ['calendar.index', ['calendar.*', 'farmer.planting-schedules.*']],
        'mao-planner' => ['mao.planner.index', ['mao.planner.*', 'calendar.*']],
        'community' => ['community-feed.index', ['community-feed.*']],
        'messages' => ['messages.index', ['messages.*']],
        'notifications' => ['notifications.index', ['notifications.*']],
        'climate-records' => ['climate-records.index', ['climate-records.*']],
        'weather' => ['weather-predictions.index', ['weather-predictions.*', 'analysis.*']],   // Manuscript – Naicel: + analysis tabs
        'rice-production' => ['rice-productions.index', ['rice-productions.*']],
        'farmer-profiles' => ['farmer-profiles.index', ['farmer-profiles.*']],
        'reports' => ['reports.index', ['reports.*']],
        'user-management' => ['users.index', ['users.*']],
        'system-logs' => ['system-logs.index', ['system-logs.*']],
        'system-health' => ['system-health.index', ['system-health.*', 'model-registry.*', 'model-evaluation.*', 'testing.*']],
        'ai-admin' => ['ai-admin.home', ['ai-admin.*']],
        // Manuscript – Naicel (2026-10-08): pages of the manuscript modules (routes/manuscript.php) open as tabs of an
        // existing item, so the menu stays short.
        'farmer-profiles-and-approvals' => ['farmer-profiles.index', ['farmer-profiles.*', 'mao.farmer-approvals.*']],
        'records-and-barangays' => ['climate-records.index', ['climate-records.*', 'rice-productions.*', 'records.*', 'mao.barangays.*']],
        // Panel revision 4.4: MAO Office = Activity Log and MAO Personnel.
        'mao-office' => ['mao.activity-log.index', ['mao.activity-log.*', 'mao.personnel.*']],
        'it-settings' => ['admin.settings.index', ['admin.settings.*', 'admin.permissions.*', 'admin.backups.*', 'admin.maintenance.*', 'admin.models.*', 'admin.feedback.*']],
        // End Manuscript – Naicel
        // Farmers: My Farm also covers their planting schedules and the Calendar it links to.
        'my-farm' => ['farmer.my-farm', ['farmer.my-farm', 'farmer.planting-schedules.*', 'calendar.*']],
        'rice-varieties' => ['rice-varieties.index', ['rice-varieties.*']],
    ];

    $navLink = function (string $label, string $routeKey, string $iconKey, ?int $badge = null) use ($routeDefs): array {
        [$route, $patterns] = $routeDefs[$routeKey];

        return [$label, $route, $patterns, $iconKey, $badge, null];
    };

    $navLinkAnchor = function (string $label, string $anchor, string $iconKey) use ($dashboardRoute): array {
        return [$label, $dashboardRoute, [$dashboardRoute], $iconKey, null, $anchor];
    };

    if ($isFarmer) {
        // Seven items in the farmer's own language (English / Filipino). The Calendar opens from My Farm; announcements
        // are read on the Advisories page; notifications open from the bell in the top bar.
        $farmerLocale = \App\Support\FarmerLanguage::resolve(request());
        $nav = fn (string $key): string => __('farmer.nav.'.$key, [], $farmerLocale);
        $primaryGroup = [
            $navLink($nav('home'), 'dashboard', 'dashboard'),
            $navLink($nav('forecast'), 'weather', 'weather'),
            $navLink($nav('heat_map'), 'heatmap', 'heatmap'),
            $navLink($nav('advisories'), 'advisories', 'advisories'),
            $navLink($nav('my_farm'), 'my-farm', 'rice-production'),
            $navLink($nav('varieties'), 'rice-varieties', 'seedling'),
            $navLink($nav('messages'), 'messages', 'messages'),
        ];
        $moreGroup = [];
        $publishingGroup = [];
    } elseif ($isMao) {
        // MAO (2026-10): eleven items in one list, each page once. Records opens Climate Records, with Rice Production
        // as its second tab. Chatbot Knowledge is an IT page.
        // MAO Dashboard – Naicel (2026-10-08): the menu follows the MAO's language (English unless Filipino was picked
        // on the MAO Dashboard): lang/{en,fil}/mao.php, section "nav".
        $maoLocale = \App\Support\MaoLanguage::of($user);
        $maoNav = fn (string $key): string => __('mao.nav.'.$key, [], $maoLocale);
        $primaryGroup = [
            $navLink($maoNav('dashboard'), 'dashboard', 'dashboard'),
            $navLink($maoNav('heat_map'), 'heatmap', 'heatmap'),
            $navLink($maoNav('advisories'), 'advisories', 'advisories'),
            // "MAO Planner", or "Planner ng MAO" when the MAO's saved language is Filipino.
            $navLink(__('mao.planner.title', [], $maoLocale), 'mao-planner', 'calendar'),
            $navLink($maoNav('records'), 'records-and-barangays', 'climate-records'),   // Manuscript – Naicel: + Import, Barangays
            $navLink($maoNav('farmers'), 'farmer-profiles-and-approvals', 'farmer-profiles'),   // Manuscript – Naicel: + Farmer Approvals tab
            $navLink($maoNav('varieties'), 'rice-varieties', 'seedling'),
            // MAO/IT – Naicel (2026-10-07): Field Reports is hidden unless FIELD_REPORTS_UI=true (config/iclimate.php).
            ...(\App\Http\Middleware\FieldReportsUi::enabled() ? [$navLink($maoNav('field_reports'), 'field-reports', 'reports')] : []),
            $navLink($maoNav('predictions'), 'weather', 'weather'),
            $navLink($maoNav('reports'), 'reports', 'reports'),
            $navLink($maoNav('announcements'), 'community', 'community'),
            $navLink($maoNav('messages'), 'messages', 'messages'),
            $navLink($maoNav('mao_office'), 'mao-office', 'system-logs'),   // panel revision 4.4
        ];
        $moreGroup = [];
        $publishingGroup = [];
    } else {
        // IT Personnel: keep the main list focused on users, reports, messages, and settings.
        // Technical pages remain available under More tools.
        $primaryGroup = [
            $navLink('IT Personnel Dashboard', 'dashboard', 'dashboard'),
            $navLink('User management', 'user-management', 'user-management'),
            $navLink('Reports', 'reports', 'reports'),
            $navLink('Rice Varieties', 'rice-varieties', 'seedling'),
            // Manuscript – Naicel (2026-10-08): System Settings, Security, Roles & Permissions, Backups, Maintenance, ML Models.
            $navLink('Settings', 'it-settings', 'system-health'),
            $navLink('Messages', 'messages', 'messages'),
        ];
        $moreGroup = [
            $navLink('System health details', 'system-health', 'system-health'),
            $navLink('Activity logs', 'system-logs', 'system-logs'),
        ];
        $publishingGroup = [];
    }

    $sidebarSections = array_values(array_filter([
        ['Menu', $primaryGroup],
        [$isFarmer ? 'My farm' : 'More tools', $moreGroup],
        ['Community and updates', $publishingGroup],
    ], fn ($section) => ! empty($section[1])));

    // Flat sections (a small label over its links); the open page, or any page under it, is marked active.
    $renderSidebarNav = function (string $idPrefix) use ($sidebarSections, $sidebarIcon): string {
        $html = '';

        foreach ($sidebarSections as $index => [$sectionLabel, $links]) {
            $labelId = $idPrefix.'-section-'.$index;
            $html .= '<div class="sidebar-group" role="group" aria-labelledby="'.$labelId.'">';
            $html .= '<div class="sidebar-section" id="'.$labelId.'">'.e($sectionLabel).'</div>';
            // --sb-count: how many links, so the stylesheet can cap how far they spread (resources/css/sidebar.css).
            $html .= '<div class="sidebar-stack" style="--sb-count: '.count($links).'">';
            foreach ($links as [$label, $route, $patterns, $iconKey, $badge, $anchor]) {
                $active = request()->routeIs(...$patterns) ? 'active' : '';
                $href = $anchor ? route($route).'#'.$anchor : route($route);
                $html .= '<a class="sidebar-link '.$active.'" href="'.e($href).'"'.($active ? ' aria-current="page"' : '').'>';
                $html .= '<span class="sidebar-icon" aria-hidden="true">'.$sidebarIcon($iconKey).'</span>';
                $html .= '<span class="sidebar-label">'.e($label).'</span>';
                if (! empty($badge)) {
                    $html .= '<span class="sidebar-badge">'.(int) $badge.'</span>';
                }
                $html .= '</a>';
            }
            $html .= '</div>';
            $html .= '</div>';
        }

        return $html;
    };

@endphp

{{-- The navigation of the application shell (layouts/app.blade.php) for Farmer, MAO and IT: a drawer opened from the
     menu button in the top bar (layouts/navigation.blade.php) at every screen size; the page behind it is blurred and
     inactive until it closes. Only the menu items differ by role. Styles: resources/css/sidebar.css and app-shell.css. --}}
<div class="ic-sidebar ic-sidebar--drawer offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="ic-sb-drawer-head">
        @include('layouts.partials.sidebar-brand')
        <h2 class="visually-hidden" id="mobileSidebarLabel">Menu</h2>
        <button type="button" class="ic-sb-close" data-bs-dismiss="offcanvas" aria-label="Close menu">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4.5 4.5l9 9M13.5 4.5l-9 9" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
        </button>
    </div>
    <nav class="ic-sb-nav sidebar-nav-scroll overflow-auto" aria-label="Main menu">
        {!! $renderSidebarNav('dr') !!}
    </nav>
    {{-- Pinned under the menu, which scrolls on its own. Farmers log out here (MAO and IT from the account menu in
         the top bar); the light/dark switch is in the top bar now, and the credit line stays. --}}
    <div class="ic-sb-foot">
        @if ($isFarmer)
            @include('layouts.partials.logout-form', ['variant' => 'sidebar', 'label' => __('farmer.nav.logout', [], $farmerLocale)])
        @endif
        <div class="sidebar-foot">&copy; 2026 iClimate Research Group<br>BatStateU ARASOF-Nasugbu</div>
    </div>
</div>
