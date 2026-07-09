@php
    $user = auth()->user();
    $isFarmer = $user->role === \App\Models\User::ROLE_FARMER;
    $isIt = $user->role === \App\Models\User::ROLE_IT_EXPERT;
    $dashboardRoute = $user->dashboardRoute();
    $dashboardLinks = [
        ['Dashboard', $dashboardRoute, [$dashboardRoute], 'D'],
    ];
    $moduleLinks = [
        ['Farmer Profiles', 'farmer-profiles.index', ['farmer-profiles.*'], 'FP'],
        ['Climate Records', 'climate-records.index', ['climate-records.*'], 'CR'],
        ['Weather Prediction', 'weather-predictions.index', ['weather-predictions.*'], 'WP'],
        ['Live Forecasting', 'live-forecasting.index', ['live-forecasting.*'], 'LF'],
        ['Rice Production', 'rice-productions.index', ['rice-productions.*'], 'RP'],
        ['Heat Map Areas', 'heatmap-areas.index', ['heatmap-areas.*'], 'HM'],
        ['Planting Advisories', 'planting-advisories.index', ['planting-advisories.*'], 'PA'],
        ['Community Feed', 'community-feed.index', ['community-feed.*'], 'CF'],
        ['Messages', 'messages.index', ['messages.*'], 'MS'],
    ];
    if (! $isFarmer) {
        $moduleLinks[] = ['Reports', 'reports.index', ['reports.*'], 'RE'];
    }
    $adminLinks = $isIt ? [
        ['User Management', 'users.index', ['users.*'], 'UM'],
        ['System Logs', 'system-logs.index', ['system-logs.*'], 'SL'],
    ] : [];
    $primaryLabels = $isFarmer
        ? ['Planting Advisories', 'Community Feed', 'Messages']
        : ($isIt
            ? ['Reports', 'User Management', 'System Logs']
            : ['Climate Records', 'Planting Advisories', 'Messages']);
    $primaryLinks = array_merge(
        $dashboardLinks,
        array_values(array_filter(array_merge($moduleLinks, $adminLinks), fn ($link) => in_array($link[0], $primaryLabels, true)))
    );
    $secondaryLinks = array_values(array_filter($moduleLinks, fn ($link) => ! in_array($link[0], $primaryLabels, true)));
    $recordsLinks = array_values(array_filter($secondaryLinks, fn ($link) => in_array($link[0], ['Farmer Profiles', 'Climate Records', 'Weather Prediction', 'Live Forecasting', 'Rice Production', 'Heat Map Areas'], true)));
    $publishingLinks = array_values(array_filter($secondaryLinks, fn ($link) => in_array($link[0], ['Planting Advisories', 'Community Feed', 'Messages', 'Reports'], true)));
    $moreGroups = [
        ['Records', $recordsLinks],
        ['Publishing', $publishingLinks],
    ];
    if ($isIt) {
        $moreGroups[] = ['Admin Tools', array_values(array_filter($adminLinks, fn ($link) => ! in_array($link[0], $primaryLabels, true)))];
    }
    $isGroupActive = fn ($links) => collect($links)->contains(fn ($link) => request()->routeIs(...$link[2]));
@endphp

<aside class="sidebar-fixed d-none d-lg-block">
    <div class="sidebar-brand p-4">
        <img src="{{ asset('images/iClimate.png') }}" alt="iClimate" class="sidebar-logo-img mb-3">
        <div class="small text-white-50 mb-1">Lian, Batangas</div>
        <div class="small text-white-50 pe-2">Weather Impact and Rice Yield System</div>
    </div>
    <div class="p-3">
        <div class="sidebar-section">Primary</div>
        <div class="d-grid gap-1">
            @foreach ($primaryLinks as [$label, $route, $patterns, $icon])
                <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                    <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                </a>
            @endforeach
        </div>
        <div class="sidebar-section">More</div>
        <div class="d-grid gap-2">
            @foreach ($moreGroups as [$groupLabel, $links])
                @continue(empty($links))
                <details class="sidebar-group" {{ $isGroupActive($links) ? 'open' : '' }}>
                    <summary><span>{{ $groupLabel }}</span><span class="sidebar-group-caret">&gt;</span></summary>
                    <div class="sidebar-group-links">
                        @foreach ($links as [$label, $route, $patterns, $icon])
                            <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                                <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</aside>

<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header" style="background: var(--ic-green-950);">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">iClimate</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0" style="background: linear-gradient(180deg, var(--ic-green-950), var(--ic-green-800));">
        <div class="sidebar-brand p-4">
            <img src="{{ asset('images/iClimate.png') }}" alt="iClimate" class="sidebar-logo-img mb-3">
            <div class="small text-white-50 mb-1">Lian, Batangas</div>
            <div class="small text-white-50">Weather Impact and Rice Yield System</div>
        </div>
        <div class="p-3">
            <div class="sidebar-section">Primary</div>
            <div class="d-grid gap-1">
                @foreach ($primaryLinks as [$label, $route, $patterns, $icon])
                    <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                        <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                    </a>
                @endforeach
            </div>
            <div class="sidebar-section">More</div>
            <div class="d-grid gap-2">
                @foreach ($moreGroups as [$groupLabel, $links])
                    @continue(empty($links))
                    <details class="sidebar-group" {{ $isGroupActive($links) ? 'open' : '' }}>
                        <summary><span>{{ $groupLabel }}</span><span class="sidebar-group-caret">&gt;</span></summary>
                        <div class="sidebar-group-links">
                            @foreach ($links as [$label, $route, $patterns, $icon])
                                <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                                    <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</div>
