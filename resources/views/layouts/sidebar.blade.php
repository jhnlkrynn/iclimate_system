@php
    $user = auth()->user();
    $isFarmer = $user->role === \App\Models\User::ROLE_FARMER;
    $isIt = $user->role === \App\Models\User::ROLE_IT_EXPERT;
    $dashboardRoute = $user->dashboardRoute();
    $links = [
        ['Dashboard', $dashboardRoute, [$dashboardRoute], 'D'],
        ['Farmer Profiles', 'farmer-profiles.index', ['farmer-profiles.*'], 'FP'],
        ['Climate Records', 'climate-records.index', ['climate-records.*'], 'CR'],
        ['Weather Prediction', 'weather-predictions.index', ['weather-predictions.*'], 'WP'],
        ['Rice Production', 'rice-productions.index', ['rice-productions.*'], 'RP'],
        ['Planting Advisories', 'planting-advisories.index', ['planting-advisories.*'], 'PA'],
        ['Announcements', 'announcements.index', ['announcements.*'], 'AN'],
        ['Notifications', 'notifications.index', ['notifications.*'], 'NT'],
        ['Heat Map Areas', 'heatmap-areas.index', ['heatmap-areas.*'], 'HM'],
    ];
    if (! $isFarmer) {
        $links[] = ['Reports', 'reports.index', ['reports.*'], 'RE'];
    }
    $adminLinks = $isIt ? [
        ['User Management', 'users.index', ['users.*'], 'UM'],
        ['System Logs', 'system-logs.index', ['system-logs.*'], 'SL'],
    ] : [];
@endphp

<aside class="sidebar-fixed d-none d-lg-block">
    <div class="sidebar-brand p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
            <div class="brand-chip"></div>
            <div>
                <div class="h4 mb-0 text-white fw-black">iClimate</div>
                <div class="small text-white-50">Lian, Batangas</div>
            </div>
        </div>
        <div class="small text-white-50 pe-2">Weather Impact and Rice Yield System</div>
    </div>
    <div class="p-3">
        <div class="sidebar-section">Main</div>
        <div class="d-grid gap-1">
            @foreach ($links as [$label, $route, $patterns, $icon])
                <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                    <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                </a>
            @endforeach
        </div>
        @if ($adminLinks)
            <div class="sidebar-section">Administration</div>
            <div class="d-grid gap-1">
                @foreach ($adminLinks as [$label, $route, $patterns, $icon])
                    <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                        <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</aside>

<div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header" style="background: var(--ic-river-deep);">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">iClimate</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0" style="background: linear-gradient(180deg, #0d2d4d, #0c6542);">
        <div class="sidebar-brand p-4">
            <div class="d-flex align-items-center gap-3 mb-3"><div class="brand-chip"></div><div><div class="h4 mb-0 text-white">iClimate</div><div class="small text-white-50">Lian, Batangas</div></div></div>
            <div class="small text-white-50">Weather Impact and Rice Yield System</div>
        </div>
        <div class="p-3">
            <div class="sidebar-section">Main</div>
            <div class="d-grid gap-1">
                @foreach ($links as [$label, $route, $patterns, $icon])
                    <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                        <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                    </a>
                @endforeach
            </div>
            @if ($adminLinks)
                <div class="sidebar-section">Administration</div>
                <div class="d-grid gap-1">
                    @foreach ($adminLinks as [$label, $route, $patterns, $icon])
                        <a class="sidebar-link {{ request()->routeIs(...$patterns) ? 'active' : '' }}" href="{{ route($route) }}">
                            <span class="d-flex align-items-center gap-2"><span class="sidebar-icon">{{ $icon }}</span><span>{{ $label }}</span></span><span class="small">&gt;</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>