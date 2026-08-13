<x-app-layout>
    <style>
        .it-console {
            --it-ink: #1F2937;
            --it-muted: #64748B;
            --it-green: #1F4D3A;
            --it-green-bright: #5F8F78;
            --it-blue: #1677b8;
            --it-gold: #E8A73D;
            --it-gold-dark: #C6872A;
            --it-red: #d85b45;
            --it-line: rgba(95,143,120,.55);
            font-family: 'Inter', system-ui, sans-serif;
        }
        .it-console h1, .it-console h2, .it-console h3 { font-family: 'DM Serif Display', Georgia, serif; font-weight: 400 !important; letter-spacing: -.01em; }
        .it-console .mono { font-family: 'DM Mono', monospace; }
        /* -- Buttons unified with landing/farmer pill style -- */
        .it-console .btn { border-radius: 999px !important; font-weight: 600 !important; letter-spacing: .01em; transition: transform .18s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease; }
        .it-console .btn-primary, .it-console .btn-light {
            background: var(--it-gold) !important; border-color: var(--it-gold) !important; color: #1F2937 !important;
            box-shadow: 0 10px 24px rgba(232,167,61,.28);
        }
        .it-console .btn-primary:hover, .it-console .btn-light:hover { background: var(--it-gold-dark) !important; border-color: var(--it-gold-dark) !important; transform: translateY(-1px); }
        .it-console .btn-outline-light { border-color: rgba(255,255,255,.55) !important; color: #fff !important; background: transparent !important; }
        .it-console .btn-outline-light:hover { background: #fff !important; border-color: #fff !important; color: var(--it-ink) !important; }
        .it-console .btn-outline-primary { border-color: var(--it-green) !important; color: var(--it-green) !important; background: transparent !important; }
        .it-console .btn-outline-primary:hover { background: var(--it-green) !important; border-color: var(--it-green) !important; color: #fff !important; transform: translateY(-1px); }
        .it-console .btn-sm { padding: .4rem .9rem; font-size: .78rem; }
        .it-hero {
            position: relative;
            overflow: hidden;
            border-radius: 32px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background: linear-gradient(145deg, #163B2D 0%, #1F4D3A 100%);
            border: 1px solid rgba(255,255,255,.12);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.24);
        }
        .it-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.05) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.04) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }
        .it-hero > * { position: relative; z-index: 1; }
        .it-eyebrow {
            font-family: 'DM Mono', monospace;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: #F6D58A;
        }
        .it-hero h1 { color: #fff; }
        .it-hero p { color: rgba(255,255,255,.72); max-width: 700px; }
        .it-live-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 999px;
            padding: .45rem .75rem;
            background: rgba(255,255,255,.1);
            color: #fff;
            font-family: 'DM Mono', monospace;
            font-size: .78rem;
            font-weight: 500;
        }
        .it-hero .btn-outline-light { border-color: rgba(255,255,255,.5); color: #fff; }
        .it-hero .btn-outline-light:hover { background: #fff; border-color: #fff; color: var(--it-ink); }
        .it-pulse {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: #E8A73D;
            box-shadow: 0 0 0 6px rgba(232,167,61,.25);
        }
        .it-stat-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .it-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--it-line);
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            width: 100%;
            text-align: left;
            font: inherit;
            cursor: pointer;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .it-card:hover { transform: translateY(-2px); box-shadow: 0 1.1rem 2.2rem rgba(20,32,51,.11); }
        .it-card:focus-visible { outline: 2px solid var(--it-green-bright); outline-offset: 2px; }
        .it-tap-hint { color: var(--it-green); font-family: 'DM Mono', monospace; font-size: .64rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: .5rem; opacity: 0; transform: translateY(2px); transition: opacity .18s ease, transform .18s ease; }
        .it-card:hover .it-tap-hint, .it-card:focus-visible .it-tap-hint { opacity: 1; transform: translateY(0); }
        .it-modal-content { border-radius: 18px; background: linear-gradient(145deg, #ffffff, #f7fbf8); color: var(--it-muted); overflow: hidden; }
        .it-modal-content .modal-footer { border-top-color: rgba(95,143,120,.4); }
        .it-modal-header { background: linear-gradient(90deg, rgba(95,143,120,.1), rgba(127,214,181,.22)); color: var(--it-ink); }
        .it-modal-header .modal-title { color: var(--it-ink); }
        .it-modal-headline { font-family: 'DM Serif Display', serif; font-size: 1.6rem; font-weight: 400; color: var(--it-ink); }
        .it-modal-sub { color: var(--it-muted); font-size: .85rem; margin: .3rem 0 .9rem; }
        .it-card-body { padding: 1rem; position: relative; z-index: 1; }
        .it-label {
            color: var(--it-muted);
            font-family: 'DM Mono', monospace;
            font-size: .68rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: .07em;
        }
        .it-value {
            margin-top: .35rem;
            font-family: 'DM Serif Display', serif;
            font-size: clamp(1.8rem, 3vw, 2.4rem);
            line-height: 1;
            font-weight: 400;
            color: var(--it-ink);
            letter-spacing: -.02em;
        }
        .it-note { color: var(--it-muted); font-size: .84rem; margin-top: .65rem; }
        .it-icon {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            border: 1px solid rgba(127,214,181,.35);
            background: rgba(95,143,120,.14);
            color: var(--it-green);
        }
        .it-icon svg { width: 19px; height: 19px; }
        .it-panel {
            border: 1px solid var(--it-line);
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            overflow: hidden;
        }
        .it-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1rem .75rem;
            border-bottom: 1px solid rgba(95,143,120,.58);
            background: rgba(95,143,120,.08);
        }
        .it-panel-title { font-size: 1.05rem; margin: 0; color: var(--it-ink); }
        .it-panel-sub { margin: .15rem 0 0; color: var(--it-muted); font-size: .82rem; }
        .it-panel-body { padding: 1rem; }
        .module-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .module-link {
            display: block;
            padding: .9rem;
            border: 1px solid rgba(95,143,120,.72);
            border-radius: 10px;
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            text-decoration: none;
            color: inherit;
            transition: transform .18s ease, border-color .18s ease, box-shadow .18s ease;
        }
        .module-link:hover { border-color: rgba(232,167,61,.55); transform: translateY(-2px); box-shadow: 0 .6rem 1.4rem rgba(20,32,51,.08); }
        .module-top { display: flex; justify-content: space-between; gap: 1rem; align-items: center; }
        .module-name { font-weight: 700; color: var(--it-ink); font-size: .9rem; }
        .module-count { font-family: 'DM Mono', monospace; font-weight: 700; color: var(--it-green); }
        .module-meter { height: 6px; border-radius: 999px; background: rgba(95,143,120,.18); overflow: hidden; margin-top: .75rem; }
        .module-meter span { display: block; height: 100%; width: var(--meter); background: linear-gradient(90deg, var(--it-green-bright), var(--it-gold)); }
        .role-row, .log-row, .user-row, .report-row {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: .8rem 0;
        }
        .role-row + .role-row, .log-row + .log-row, .user-row + .user-row, .report-row + .report-row { border-top: 1px solid rgba(95,143,120,.55); }
        .avatar-soft {
            width: 38px;
            height: 38px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            background: linear-gradient(135deg, var(--it-green-bright), var(--it-green));
            color: #fff;
            font-family: 'DM Mono', monospace;
            font-weight: 700;
            font-size: .74rem;
            flex: 0 0 auto;
        }
        .log-dot {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: rgba(95,143,120,.14);
            border: 1px solid rgba(127,214,181,.35);
            color: var(--it-green);
            flex: 0 0 auto;
        }
        .log-dot svg { width: 17px; height: 17px; }
        .row-title { font-weight: 700; color: var(--it-ink); line-height: 1.25; }
        .row-sub { color: var(--it-muted); font-size: .82rem; margin-top: .12rem; }
        .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .quick-action {
            min-height: 104px;
            padding: 1rem;
            border-radius: 10px;
            border: 1px solid rgba(95,143,120,.72);
            background: linear-gradient(135deg, rgba(95,143,120,.08), rgba(127,214,181,.08));
            color: inherit;
            text-decoration: none;
        }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(31,77,58,.55); }
        .quick-action strong { display: block; color: var(--it-ink); }
        .quick-action span { display: block; color: var(--it-muted); font-size: .82rem; margin-top: .25rem; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .3rem .62rem;
            font-family: 'DM Mono', monospace;
            font-size: .64rem;
            font-weight: 700;
            letter-spacing: .03em;
            text-transform: uppercase;
            background: rgba(95,143,120,.14);
            color: var(--it-green);
        }
        .status-pill.muted { background: rgba(95,143,120,.1); color: var(--it-muted); }
        .dashboard-section-label {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            margin: 1.35rem 0 .75rem;
        }
        .dashboard-section-label:first-of-type { margin-top: 0; }
        .section-title {
            color: #F6D58A;
            font-family: 'DM Mono', monospace !important;
            font-size: .74rem;
            font-weight: 600 !important;
            text-transform: uppercase;
            letter-spacing: .1em;
            margin: 0;
        }
        .section-note { color: #7FD6B5; font-size: .84rem; margin: 0; }
        .dashboard-focus-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        .dashboard-group {
            border: 1px solid var(--it-line);
            border-radius: 18px;
            background: linear-gradient(145deg, #ffffff, #f7fbf8);
            box-shadow: 0 .55rem 1.35rem rgba(20,32,51,.045);
            overflow: hidden;
        }
        .dashboard-group > summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: .95rem 1rem;
            cursor: pointer;
            list-style: none;
            background: linear-gradient(135deg, rgba(95,143,120,.08), rgba(127,214,181,.08));
        }
        .dashboard-group > summary::-webkit-details-marker { display: none; }
        .dashboard-group-title { color: var(--it-ink); font-family: 'Inter', sans-serif !important; font-size: .92rem; font-weight: 700 !important; text-transform: none; letter-spacing: -.005em; margin: 0; }
        .dashboard-group-note { color: var(--it-muted); font-size: .82rem; margin: .1rem 0 0; }
        .dashboard-group-toggle { color: var(--it-green); font-size: .78rem; font-weight: 700; white-space: nowrap; }
        .dashboard-group-toggle::after { content: "Collapse"; }
        .dashboard-group:not([open]) .dashboard-group-toggle::after { content: "Expand"; }
        .dashboard-group-body { padding: 1rem; }
        .dashboard-group-body > .row:last-child { margin-bottom: 0 !important; }
        .list-compact .log-row:nth-of-type(n+5), .list-compact .user-row:nth-of-type(n+5), .list-compact .report-row:nth-of-type(n+5) { display: none; }
        @media (max-width: 1399.98px) { .it-stat-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
        @media (max-width: 1199.98px) { .it-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .it-stat-grid, .module-list, .quick-grid { grid-template-columns: 1fr; } .it-panel-header, .dashboard-section-label, .dashboard-group > summary { align-items: flex-start; flex-direction: column; } }

        .it-console .empty-state { background: linear-gradient(135deg, rgba(95,143,120,.08), rgba(127,214,181,.08)); border: 1px dashed rgba(95,143,120,.55); color: var(--it-muted); border-radius: 8px; }
        .it-console .empty-state .fw-bold { color: var(--it-ink); }
    </style>

    @php
        $moduleRoutes = [
            'Farmer Profiles' => 'farmer-profiles.index',
            'Climate Records' => 'climate-records.index',
            'Rice Productions' => 'rice-productions.index',
            'Advisories' => 'planting-advisories.index',
            'Community Feed' => 'community-feed.index',
            'Notifications' => 'notifications.index',
            'Heat Map Areas' => 'heatmap-areas.index',
            'Reports' => 'reports.index',
        ];
        $moduleTotal = max(array_sum($moduleCounts), 1);
        $healthScore = $userCount > 0 ? round(($activeUsers / max($userCount, 1)) * 100) : 100;
    @endphp

    <div class="it-console">
        <section class="it-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="it-eyebrow mb-2">IT Expert Console</div>
                    <h1 class="h2 fw-bold mb-2">System command center</h1>
                    <p class="mb-0">Monitor user access, heat map risk records, reports, and system activity for the iClimate platform in one operational dashboard.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="it-live-chip"><span class="it-pulse"></span> System Online</span>
                    <a class="btn btn-light fw-bold" href="{{ route('heatmap-areas.index') }}">Heat Map</a>
                    <a class="btn btn-light fw-bold" href="{{ route('users.index') }}">Manage Users</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('system-logs.index') }}">View Logs</a>
                </div>
            </div>
        </section>

        <div class="dashboard-section-label">
            <div>
                <h2 class="section-title">System Overview</h2>
                <p class="section-note">Heat map, user, farmer, audit, and report status at a glance.</p>
            </div>
        </div>

        <section class="it-stat-grid">
            <button type="button" class="it-card text-decoration-none" data-drawer-open="#itStatUsers">
                <div class="it-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div><div class="it-label">Total Users</div><div class="it-value">{{ number_format($userCount) }}</div></div>
                        <div class="it-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="7" cy="7" r="2.6" stroke="currentColor" stroke-width="1.4"/><circle cx="14" cy="8" r="2.2" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 16c.5-3 2-4.5 4.5-4.5s4 1.5 4.5 4.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><path d="M11.5 16c.4-2.4 1.7-3.7 3.5-3.7s3 1.1 3.5 3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg></div>
                    </div>
                    <div class="it-note">{{ number_format($activeUsers) }} active, {{ number_format($inactiveUsers) }} inactive</div>
                    <div class="it-tap-hint">View details &rarr;</div>
                </div>
            </button>
            <button type="button" class="it-card text-decoration-none" data-drawer-open="#itStatFarmers">
                <div class="it-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div><div class="it-label">Farmers</div><div class="it-value">{{ number_format($totalFarmers) }}</div></div>
                        <div class="it-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><circle cx="10" cy="6.5" r="3" stroke="currentColor" stroke-width="1.5"/><path d="M3.5 18c.6-4.8 3-7.5 6.5-7.5s5.9 2.7 6.5 7.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg></div>
                    </div>
                    <div class="it-note">Registered farmer access accounts</div>
                    <div class="it-tap-hint">View details &rarr;</div>
                </div>
            </button>
            <button type="button" class="it-card text-decoration-none" data-drawer-open="#itStatLogs">
                <div class="it-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div><div class="it-label">System Logs</div><div class="it-value">{{ number_format($logCount) }}</div></div>
                        <div class="it-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="4" y="3" width="12" height="14" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M7 7h6M7 10h6M7 13h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                    </div>
                    <div class="it-note">Audit trail events recorded</div>
                    <div class="it-tap-hint">View details &rarr;</div>
                </div>
            </button>
            <button type="button" class="it-card text-decoration-none" data-drawer-open="#itStatHeatmap">
                <div class="it-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div><div class="it-label">Heat Map Areas</div><div class="it-value">{{ number_format($moduleCounts['Heat Map Areas'] ?? 0) }}</div></div>
                        <div class="it-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M10 18s6-6 6-10.5A6 6 0 0 0 4 7.5C4 12 10 18 10 18Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><circle cx="10" cy="7.5" r="2.2" stroke="currentColor" stroke-width="1.3"/></svg></div>
                    </div>
                    <div class="it-note">{{ number_format($highRiskHeatMapAreas) }} high or severe risk areas</div>
                    <div class="it-tap-hint">View details &rarr;</div>
                </div>
            </button>
            <button type="button" class="it-card text-decoration-none" data-drawer-open="#itStatReports">
                <div class="it-card-body">
                    <div class="d-flex justify-content-between gap-3">
                        <div><div class="it-label">Reports</div><div class="it-value">{{ number_format($moduleCounts['Reports'] ?? 0) }}</div></div>
                        <div class="it-icon"><svg width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M4 3h10l4 4v12H4V3z" stroke="currentColor" stroke-width="1.4"/><path d="M14 3v4h4" stroke="currentColor" stroke-width="1.4"/><path d="M7 10h8M7 13h6M7 16h7" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/></svg></div>
                    </div>
                    <div class="it-note">Generated report history</div>
                    <div class="it-tap-hint">View details &rarr;</div>
                </div>
            </button>
        </section>

        <div class="ic-drawer" id="itStatUsers" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="itStatUsersLabel">
                <div class="modal-content border-0 it-modal-content">
                    <div class="modal-header it-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="itStatUsersLabel">Total Users</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="it-modal-headline">{{ number_format($userCount) }} accounts</div>
                        <p class="it-modal-sub">{{ number_format($activeUsers) }} active &middot; {{ number_format($inactiveUsers) }} inactive.</p>
                        @foreach($roleCounts as $role => $count)
                            <div class="role-row">
                                <div class="avatar-soft">{{ collect(explode(' ', $role))->map(fn ($part) => $part[0] ?? '')->take(2)->implode('') }}</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $role }}</div>
                                    <div class="row-sub">{{ number_format($count) }} account{{ $count === 1 ? '' : 's' }}</div>
                                </div>
                                <span class="status-pill muted">{{ $userCount ? round(($count / $userCount) * 100) : 0 }}%</span>
                            </div>
                        @endforeach
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('users.index') }}">Manage Users</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="ic-drawer" id="itStatFarmers" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="itStatFarmersLabel">
                <div class="modal-content border-0 it-modal-content">
                    <div class="modal-header it-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="itStatFarmersLabel">Farmer Accounts</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="it-modal-headline">{{ number_format($totalFarmers) }} registered</div>
                        <p class="it-modal-sub">Most recently created farmer accounts.</p>
                        @forelse($recentFarmers as $user)
                            <div class="user-row">
                                <div class="avatar-soft">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $user->name }}</div>
                                    <div class="row-sub">{{ $user->email }} &middot; {{ $user->barangay ?? 'Barangay not set' }}</div>
                                </div>
                                <span class="status-pill {{ $user->status === 'Active' ? '' : 'muted' }}">{{ $user->status }}</span>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4"><div class="fw-bold">No farmer accounts yet</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('farmer-profiles.index') }}">Open Farmer Profiles</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="ic-drawer" id="itStatLogs" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="itStatLogsLabel">
                <div class="modal-content border-0 it-modal-content">
                    <div class="modal-header it-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="itStatLogsLabel">System Logs</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="it-modal-headline">{{ number_format($logCount) }} events</div>
                        <p class="it-modal-sub">Most recent audit trail activity.</p>
                        @forelse($latestLogs as $log)
                            <div class="log-row">
                                <div class="log-dot"><svg width="17" height="17" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $log->action }}</div>
                                    <div class="row-sub">{{ $log->user?->name ?? 'System' }} &middot; {{ $log->created_at?->shortDateTime('M d, Y') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4"><div class="fw-bold">No logs yet</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('system-logs.index') }}">Open Logs</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="ic-drawer" id="itStatHeatmap" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="itStatHeatmapLabel">
                <div class="modal-content border-0 it-modal-content">
                    <div class="modal-header it-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="itStatHeatmapLabel">Heat Map Areas</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="it-modal-headline">{{ number_format($highRiskHeatMapAreas) }} high or severe</div>
                        <p class="it-modal-sub">Out of {{ number_format($moduleCounts['Heat Map Areas'] ?? 0) }} mapped barangay records.</p>
                        @forelse($highRiskAreasList as $area)
                            <div class="log-row">
                                <div class="log-dot"><svg width="17" height="17" viewBox="0 0 20 20" fill="none"><path d="M10 2.5 18 16.5H2L10 2.5Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M10 8v3.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/><circle cx="10" cy="14" r=".9" fill="currentColor"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $area->barangay }} &middot; {{ $area->risk_type }}</div>
                                    <div class="row-sub">{{ $area->planting_advisory ?: 'Review latest climate and rice production data before planting.' }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4"><div class="fw-bold">No high risk barangays</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('heatmap-areas.index') }}">Open Heat Map</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="ic-drawer" id="itStatReports" aria-hidden="true">
            <div class="ic-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="itStatReportsLabel">
                <div class="modal-content border-0 it-modal-content">
                    <div class="modal-header it-modal-header">
                        <h2 class="modal-title h5 fw-bold" id="itStatReportsLabel">Reports</h2>
                        <button type="button" class="btn-close" data-drawer-close aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="it-modal-headline">{{ number_format($moduleCounts['Reports'] ?? 0) }} generated</div>
                        <p class="it-modal-sub">Most recent report history.</p>
                        @forelse($latestReports as $report)
                            <div class="report-row">
                                <div class="log-dot"><svg width="17" height="17" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $report->title }}</div>
                                    <div class="row-sub">{{ $report->report_type }} &middot; {{ $report->generatedBy?->name ?? 'System' }} &middot; {{ $report->created_at?->format('M d, Y') }}</div>
                                </div>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4"><div class="fw-bold">No reports yet</div></div>
                        @endforelse
                    </div>
                    <div class="modal-footer">
                        <a class="btn btn-primary fw-bold" href="{{ route('reports.index') }}">Open Reports</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-focus-grid">
        <details class="dashboard-group" open>
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Module And Access Health</h2>
                    <p class="dashboard-group-note">Record coverage by module and role distribution.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-8">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">Module Statistics</h2>
                            <p class="it-panel-sub">Live counts from each managed iClimate module.</p>
                        </div>
                        <span class="status-pill">{{ number_format($moduleTotal) }} total records</span>
                    </div>
                    <div class="it-panel-body">
                        <div class="module-list">
                            @foreach($moduleCounts as $module => $count)
                                @php
                                    $routeName = $moduleRoutes[$module] ?? 'admin.dashboard';
                                    $meter = min(100, max(8, round(($count / $moduleTotal) * 100)));
                                @endphp
                                <a class="module-link" href="{{ route($routeName) }}">
                                    <div class="module-top">
                                        <div class="module-name">{{ $module }}</div>
                                        <div class="module-count">{{ number_format($count) }}</div>
                                    </div>
                                    <div class="module-meter" style="--meter: {{ $meter }}%;"><span></span></div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">Access Health</h2>
                            <p class="it-panel-sub">Role distribution and account status.</p>
                        </div>
                        <span class="status-pill {{ $healthScore < 80 ? 'muted' : '' }}">{{ $healthScore }}% active</span>
                    </div>
                    <div class="it-panel-body">
                        @foreach($roleCounts as $role => $count)
                            <div class="role-row">
                                <div class="avatar-soft">{{ collect(explode(' ', $role))->map(fn ($part) => $part[0] ?? '')->take(2)->implode('') }}</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $role }}</div>
                                    <div class="row-sub">{{ number_format($count) }} account{{ $count === 1 ? '' : 's' }}</div>
                                </div>
                                <span class="status-pill muted">{{ $userCount ? round(($count / $userCount) * 100) : 0 }}%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">System Activity</h2>
                    <p class="dashboard-group-note">Recent events and newly created user accounts.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4 mb-0">
            <div class="col-xl-7">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">Recent System Activity Log</h2>
                            <p class="it-panel-sub">Latest recorded events across the system.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('system-logs.index') }}">Open Logs</a>
                    </div>
                    <div class="it-panel-body list-compact">
                        @forelse($latestLogs as $log)
                            <div class="log-row">
                                <div class="log-dot"><svg width="17" height="17" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="6.5" stroke="currentColor" stroke-width="1.4"/><path d="M9 5.5V9l3 1.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $log->action }}</div>
                                    <div class="row-sub">{{ $log->user?->name ?? 'System' }} | {{ $log->created_at?->shortDateTime('M d, Y') }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4">
                                <div class="fw-bold">No logs yet</div>
                                <div class="small text-muted">System activity will appear here after users start working.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">New User Accounts</h2>
                            <p class="it-panel-sub">Recently created users in the platform.</p>
                        </div>
                        <a class="btn btn-sm btn-primary" href="{{ route('users.create') }}">Add User</a>
                    </div>
                    <div class="it-panel-body list-compact">
                        @forelse($latestUsers as $user)
                            <div class="user-row">
                                <div class="avatar-soft">{{ strtoupper(substr($user->name, 0, 2)) }}</div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $user->name }}</div>
                                    <div class="row-sub">{{ $user->email }} | {{ $user->role }}</div>
                                </div>
                                <span class="status-pill {{ $user->status === 'Active' ? '' : 'muted' }}">{{ $user->status }}</span>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4">
                                <div class="fw-bold">No users found</div>
                                <div class="small text-muted">Create an account to populate this list.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>

        <details class="dashboard-group">
            <summary>
                <div>
                    <h2 class="dashboard-group-title">Administration</h2>
                    <p class="dashboard-group-note">Common maintenance actions and report history.</p>
                </div>
                <span class="dashboard-group-toggle"></span>
            </summary>
            <div class="dashboard-group-body">
        <div class="row g-4">
            <div class="col-xl-5">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">Quick Actions</h2>
                            <p class="it-panel-sub">Common IT Expert operations.</p>
                        </div>
                    </div>
                    <div class="it-panel-body list-compact">
                        <div class="quick-grid">
                            <a class="quick-action" href="{{ route('users.create') }}"><strong>Add New User</strong><span>Create Farmer, MAO Personnel, or IT Expert accounts.</span></a>
                            <a class="quick-action" href="{{ route('reports.index') }}"><strong>Reports Center</strong><span>Generate and review platform reports.</span></a>
                            <a class="quick-action" href="{{ route('heatmap-areas.index') }}"><strong>Heat Map Manager</strong><span>Review barangay climate risk areas and severity records.</span></a>
                            <a class="quick-action" href="{{ route('system-logs.index') }}"><strong>Audit Trail</strong><span>Inspect recent system actions and changes.</span></a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-7">
                <div class="it-panel h-100">
                    <div class="it-panel-header">
                        <div>
                            <h2 class="it-panel-title">Recent Reports</h2>
                            <p class="it-panel-sub">Latest generated report history.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">View Reports</a>
                    </div>
                    <div class="it-panel-body">
                        @forelse($latestReports as $report)
                            <div class="report-row">
                                <div class="log-dot"><svg width="17" height="17" viewBox="0 0 18 18" fill="none"><path d="M3.5 2.5h8l3 3v10h-11v-13z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M6 9h6M6 12h4" stroke="currentColor" stroke-width="1.3" stroke-linecap="round"/></svg></div>
                                <div class="flex-grow-1">
                                    <div class="row-title">{{ $report->title }}</div>
                                    <div class="row-sub">{{ $report->report_type }} | {{ $report->generatedBy?->name ?? 'System' }} | {{ $report->created_at?->format('M d, Y') }}</div>
                                </div>
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a>
                            </div>
                        @empty
                            <div class="empty-state text-center p-4">
                                <div class="fw-bold">No reports yet</div>
                                <div class="small text-muted">Generated reports will appear here.</div>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
            </div>
        </details>
        </div>
    </div>
</x-app-layout>
