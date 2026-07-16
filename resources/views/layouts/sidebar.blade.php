@php
    $user = auth()->user();
    $isFarmer = $user->role === \App\Models\User::ROLE_FARMER;
    $isMao = $user->role === \App\Models\User::ROLE_MAO;
    $isIt = $user->role === \App\Models\User::ROLE_IT_EXPERT;
    $dashboardRoute = $user->dashboardRoute();
    $unreadNotifications = $isFarmer
        ? \App\Models\Notification::query()->where('user_id', $user->id)->where('is_read', false)->count()
        : 0;

    $sidebarIcon = function (string $key): string {
        return match ($key) {
            'dashboard' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M2.5 8.5 9 3l6.5 5.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M4 7.5V15h10V7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'advisories' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 2.5 16 15H2L9 2.5Z" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"/><path d="M9 8v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><circle cx="9" cy="13" r=".9" fill="currentColor"/></svg>',
            'calendar' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2.5" y="3.5" width="13" height="12" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="M2.5 7.5h13M6 2v3M12 2v3" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>',
            'community' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="6.5" cy="6.5" r="2.2" stroke="currentColor" stroke-width="1.4"/><circle cx="12.5" cy="7.5" r="1.8" stroke="currentColor" stroke-width="1.4"/><path d="M2 15c.5-2.7 2-4.2 4.5-4.2s4 1.5 4.5 4.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M11 15c.4-2.2 1.6-3.4 3.2-3.4s2.8 1 3.2 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'messages' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="2" y="4" width="14" height="10" rx="2" stroke="currentColor" stroke-width="1.5"/><path d="m2.5 5 6.5 5 6.5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'notifications' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5 7.2C5 4.6 6.8 3 9 3s4 1.6 4 4.2c0 3.6 1.3 4.6 1.3 4.6H3.7S5 10.8 5 7.2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.3 14.5a1.8 1.8 0 0 0 3.4 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'climate-records' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5 2.5h5.5L14 6v9.5H5V2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7 8h4M7 11h4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'weather' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M5.5 10.5a3 3 0 0 1 .6-5.9 4 4 0 0 1 7.7.7A2.7 2.7 0 0 1 13.5 10.5h-8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 13.5v1M9 13.5v1.6M12 13.5v1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'heatmap' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 2c2.8 0 5 2.3 5 5.2C14 11 9 16 9 16S4 11 4 7.2C4 4.3 6.2 2 9 2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="9" cy="7.2" r="1.7" stroke="currentColor" stroke-width="1.3"/></svg>',
            'rice-production' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M9 2c1.4 2 2.5 3.6 2.5 5.3A2.5 2.5 0 1 1 6.5 7.3C6.5 5.6 7.6 4 9 2Z" stroke="currentColor" stroke-width="1.4"/><path d="M9 9.8V16" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M9 12.5c-1.6 0-2.8.9-3.2 2.5M9 12.5c1.6 0 2.8.9 3.2 2.5" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg>',
            'farmer-profiles' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'live-forecasting' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="3" y="9.5" width="2.6" height="5.5" fill="currentColor"/><rect x="7.7" y="6" width="2.6" height="9" fill="currentColor" opacity=".85"/><rect x="12.4" y="3" width="2.6" height="12" fill="currentColor" opacity=".6"/></svg>',
            'reports' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="3.5" y="2.5" width="11" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M6.5 6.5h5M6.5 9.5h5M6.5 12.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'user-management' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><circle cx="7" cy="6.5" r="2.3" stroke="currentColor" stroke-width="1.4"/><path d="M2.3 15c.6-3 2.3-4.6 4.7-4.6s4.1 1.6 4.7 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M12 4.5a2.2 2.2 0 0 1 0 4.3M14.2 10.6c1.5.5 2.4 1.9 2.8 4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'system-logs' => '<svg width="16" height="16" viewBox="0 0 18 18" fill="none"><rect x="4" y="2.5" width="10" height="13" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M7 2.5v-.2a1.2 1.2 0 0 1 1.2-1h1.6a1.2 1.2 0 0 1 1.2 1v.2" stroke="currentColor" stroke-width="1.3"/><path d="M6.5 8h5M6.5 11h5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            'ai' => '<svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="4" y="7" width="12" height="9" rx="3" stroke="currentColor" stroke-width="1.5"/><circle cx="7.8" cy="11.5" r="1" fill="currentColor"/><circle cx="12.2" cy="11.5" r="1" fill="currentColor"/><path d="M10 7V4M7.5 4.5l1-1.2M12.5 4.5l-1-1.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>',
            default => '',
        };
    };

    $routeDefs = [
        'dashboard' => [$dashboardRoute, [$dashboardRoute]],
        'advisories' => ['planting-advisories.index', ['planting-advisories.*']],
        'calendar' => ['planting-advisories.index', ['planting-advisories.*']],
        'community' => ['community-feed.index', ['community-feed.*']],
        'messages' => ['messages.index', ['messages.*']],
        'notifications' => ['notifications.index', ['notifications.*']],
        'climate-records' => ['climate-records.index', ['climate-records.*']],
        'weather' => ['weather-predictions.index', ['weather-predictions.*']],
        'live-forecasting' => ['live-forecasting.index', ['live-forecasting.*']],
        'heatmap' => ['heatmap-areas.index', ['heatmap-areas.*']],
        'rice-production' => ['rice-productions.index', ['rice-productions.*']],
        'farmer-profiles' => ['farmer-profiles.index', ['farmer-profiles.*']],
        'reports' => ['reports.index', ['reports.*']],
        'user-management' => ['users.index', ['users.*']],
        'system-logs' => ['system-logs.index', ['system-logs.*']],
    ];

    $navLink = function (string $label, string $routeKey, string $iconKey, ?int $badge = null) use ($routeDefs): array {
        [$route, $patterns] = $routeDefs[$routeKey];

        return [$label, $route, $patterns, $iconKey, $badge];
    };

    if ($isFarmer) {
        $primaryGroup = [
            $navLink('Dashboard', 'dashboard', 'dashboard'),
            $navLink('Advisories', 'advisories', 'advisories'),
            $navLink('Calendar', 'calendar', 'calendar'),
            $navLink('Community', 'community', 'community'),
            $navLink('Messages', 'messages', 'messages'),
            $navLink('Notifications', 'notifications', 'notifications', $unreadNotifications ?: null),
        ];
        $moreGroup = [
            $navLink('Climate Records', 'climate-records', 'climate-records'),
            $navLink('Weather Forecast', 'weather', 'weather'),
            $navLink('Heat Map', 'heatmap', 'heatmap'),
            $navLink('Rice Production', 'rice-production', 'rice-production'),
        ];
        $publishingGroup = [];
    } elseif ($isMao) {
        $primaryGroup = [
            $navLink('Dashboard', 'dashboard', 'dashboard'),
            $navLink('Climate Records', 'climate-records', 'climate-records'),
            $navLink('Planting Advisories', 'advisories', 'advisories'),
            $navLink('Messages', 'messages', 'messages'),
        ];
        $moreGroup = [
            $navLink('Farmer Profiles', 'farmer-profiles', 'farmer-profiles'),
            $navLink('Weather Prediction', 'weather', 'weather'),
            $navLink('Live Forecasting', 'live-forecasting', 'live-forecasting'),
            $navLink('Rice Production', 'rice-production', 'rice-production'),
            $navLink('Heat Map Areas', 'heatmap', 'heatmap'),
        ];
        $publishingGroup = [
            $navLink('Community Feed', 'community', 'community'),
            $navLink('Reports', 'reports', 'reports'),
        ];
    } else {
        $primaryGroup = [
            $navLink('Dashboard', 'dashboard', 'dashboard'),
            $navLink('Reports', 'reports', 'reports'),
            $navLink('User Management', 'user-management', 'user-management'),
            $navLink('System Logs', 'system-logs', 'system-logs'),
        ];
        $moreGroup = [
            $navLink('Farmer Profiles', 'farmer-profiles', 'farmer-profiles'),
            $navLink('Climate Records', 'climate-records', 'climate-records'),
            $navLink('Weather Prediction', 'weather', 'weather'),
            $navLink('Live Forecasting', 'live-forecasting', 'live-forecasting'),
            $navLink('Rice Production', 'rice-production', 'rice-production'),
            $navLink('Heat Map Areas', 'heatmap', 'heatmap'),
        ];
        $publishingGroup = [
            $navLink('Planting Advisories', 'advisories', 'advisories'),
            $navLink('Community Feed', 'community', 'community'),
            $navLink('Messages', 'messages', 'messages'),
        ];
    }

    $sidebarSections = array_values(array_filter([
        ['Primary', $primaryGroup],
        ['More', $moreGroup],
        ['Publishing', $publishingGroup],
    ], fn ($section) => ! empty($section[1])));

    $renderSidebarNav = function () use ($sidebarSections, $sidebarIcon): string {
        $html = '';

        foreach ($sidebarSections as [$sectionLabel, $links]) {
            $html .= '<div class="sidebar-section">'.e($sectionLabel).'</div>';
            $html .= '<div class="d-grid gap-1 mb-2">';
            foreach ($links as [$label, $route, $patterns, $iconKey, $badge]) {
                $active = request()->routeIs(...$patterns) ? 'active' : '';
                $html .= '<a class="sidebar-link '.$active.'" href="'.e(route($route)).'">';
                $html .= '<span class="d-flex align-items-center gap-2"><span class="sidebar-icon">'.$sidebarIcon($iconKey).'</span><span>'.e($label).'</span></span>';
                $html .= '<span class="d-flex align-items-center gap-2">';
                if (! empty($badge)) {
                    $html .= '<span class="sidebar-badge">'.(int) $badge.'</span>';
                }
                $html .= '<svg class="sidebar-link-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                $html .= '</span></a>';
            }
            $html .= '</div>';
        }

        return $html;
    };

    $renderAiCard = function () use ($sidebarIcon): string {
        return '<div class="sidebar-ai-card" onclick="document.getElementById(\'icAiWidget\')?.classList.add(\'open\'); document.getElementById(\'icAiInput\')?.focus();">'
            .'<span class="sidebar-ai-icon">'.$sidebarIcon('ai').'</span>'
            .'<span><span class="sidebar-ai-title d-block">AI Assistant</span><span class="sidebar-ai-sub">Ask me anything!</span></span>'
            .'</div>';
    };
@endphp

<aside class="sidebar-fixed d-none d-lg-flex flex-column">
    @if ($isFarmer)
        <div class="sidebar-brand sidebar-brand--large p-4 pb-2">
            <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="sidebar-logo-large">
            <div class="sidebar-wordmark-lg">iClimate</div>
            <div class="sidebar-brand-underline"></div>
        </div>
    @else
        <div class="sidebar-brand p-4 pb-2">
            <div class="sidebar-brand-row">
                <img src="{{ asset('images/iclimate-logo.png') }}" alt="" class="sidebar-logo-icon">
                <span class="sidebar-wordmark">iClimate</span>
            </div>
            <div class="sidebar-brand-underline"></div>
            <div class="sidebar-location"><span class="pulse-dot"></span> Lian, Batangas</div>
            <div class="sidebar-tagline pe-2">Weather Impact and Rice Yield System</div>
        </div>
    @endif
    <div class="px-3 flex-grow-1 overflow-auto">
        {!! $renderSidebarNav() !!}
    </div>
    {!! $renderAiCard() !!}
    <div class="sidebar-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
</aside>

<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header" style="background: var(--ic-green-950);">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">iClimate</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column" style="background: var(--ic-green-950);">
        @if ($isFarmer)
            <div class="sidebar-brand sidebar-brand--large p-4 pb-2">
                <img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" class="sidebar-logo-large">
                <div class="sidebar-wordmark-lg">iClimate</div>
                <div class="sidebar-brand-underline"></div>
            </div>
        @else
            <div class="sidebar-brand p-4 pb-2">
                <div class="sidebar-brand-row">
                    <img src="{{ asset('images/iclimate-logo.png') }}" alt="" class="sidebar-logo-icon">
                    <span class="sidebar-wordmark">iClimate</span>
                </div>
                <div class="sidebar-brand-underline"></div>
                <div class="sidebar-location"><span class="pulse-dot"></span> Lian, Batangas</div>
                <div class="sidebar-tagline">Weather Impact and Rice Yield System</div>
            </div>
        @endif
        <div class="px-3 flex-grow-1 overflow-auto">
            {!! $renderSidebarNav() !!}
        </div>
        {!! $renderAiCard() !!}
        <div class="sidebar-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
    </div>
</div>
