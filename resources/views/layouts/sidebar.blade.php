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
    $recordsLinks = array_values(array_filter($secondaryLinks, fn ($link) => in_array($link[0], ['Farmer Profiles', 'Climate Records', 'Weather Prediction', 'Rice Production', 'Heat Map Areas'], true)));
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

<aside class="sidebar-fixed d-none d-lg-flex flex-column">
    <div class="sidebar-brand p-4">
        <div class="sidebar-brand-row">
            <img src="{{ asset('images/iClimate-icon.png') }}" alt="" class="sidebar-logo-icon">
            <span class="sidebar-wordmark">iClimate<span class="sidebar-wordmark-dot">.</span></span>
        </div>
        <div class="sidebar-location"><span class="pulse-dot"></span> Lian, Batangas</div>
        <div class="sidebar-tagline pe-2">Weather Impact and Rice Yield System</div>
    </div>
    <div class="p-3 flex-grow-1">
        <div class="sidebar-section">Primary</div>
        <div class="d-grid gap-1">
            @foreach ($primaryLinks as [$label, $route, $patterns, $icon])
                <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                    <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><svg class="sidebar-link-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
                                <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><svg class="sidebar-link-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        @endforeach
                    </div>
                </details>
            @endforeach
        </div>
    </div>
    <div class="sidebar-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
</aside>

<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header" style="background: var(--ic-green-950);">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">iClimate</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column" style="background: var(--ic-green-950);">
        <div class="sidebar-brand p-4">
            <div class="sidebar-brand-row">
            <img src="{{ asset('images/iClimate-icon.png') }}" alt="" class="sidebar-logo-icon">
            <span class="sidebar-wordmark">iClimate<span class="sidebar-wordmark-dot">.</span></span>
        </div>
            <div class="sidebar-location"><span class="pulse-dot"></span> Lian, Batangas</div>
            <div class="sidebar-tagline">Weather Impact and Rice Yield System</div>
        </div>
        <div class="p-3 flex-grow-1">
            <div class="sidebar-section">Primary</div>
            <div class="d-grid gap-1">
                @foreach ($primaryLinks as [$label, $route, $patterns, $icon])
                    <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                        <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><svg class="sidebar-link-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
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
                                    <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><svg class="sidebar-link-arrow" width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M5 3l4 4-4 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </a>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
        <div class="sidebar-foot">&copy; 2026 iClimate &middot; Lian, Batangas</div>
    </div>
</div>
