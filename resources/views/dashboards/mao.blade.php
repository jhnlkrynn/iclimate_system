<x-app-layout>
    <style>
        .mao-console {
            --mao-ink: #0d1f18;
            --mao-muted: #5f7569;
            --mao-green: #2d6a4f;
            --mao-green-bright: #52b788;
            --mao-blue: #1677b8;
            --mao-gold: #f4b63f;
            --mao-red: #d85b45;
            --mao-line: rgba(153, 185, 160, .72);
        }
        .mao-hero {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                radial-gradient(circle at 84% 12%, rgba(82,183,136,.26), transparent 30%),
                linear-gradient(135deg, #0d1f18 0%, #146b78 48%, #0d6a41 100%);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18);
        }
        .mao-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }
        .mao-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 7px;
            background: linear-gradient(90deg, var(--mao-gold), var(--mao-green-bright), var(--mao-blue), var(--mao-red));
        }
        .mao-hero > * { position: relative; z-index: 1; }
        .mao-eyebrow {
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.68);
        }
        .mao-hero h1 { color: #fff; }
        .mao-hero em { color: #9be5ba; font-style: italic; }
        .mao-hero p { color: rgba(255,255,255,.66); max-width: 760px; }
        .mao-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            padding: .45rem .75rem;
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.88);
            font-size: .82rem;
            font-weight: 800;
        }
        .mao-pulse { width: 10px; height: 10px; border-radius: 999px; background: var(--mao-green-bright); box-shadow: 0 0 0 6px rgba(82,183,136,.16); }
        .mao-stat-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-bottom: 1.25rem; }
        .mao-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--mao-line);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(229,242,226,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .mao-card:hover { transform: translateY(-2px); box-shadow: 0 1.1rem 2.2rem rgba(20,32,51,.11); border-color: rgba(31,143,85,.42); }
        .mao-card::before { content: ""; position: absolute; inset: 0 0 auto; height: 5px; background: var(--accent, var(--mao-green-bright)); }
        .mao-card::after { content: ""; position: absolute; right: -28px; bottom: -30px; width: 98px; height: 98px; border-radius: 999px; background: color-mix(in srgb, var(--accent, var(--mao-green-bright)) 18%, transparent); }
        .mao-card-body { padding: 1rem; position: relative; z-index: 1; }
        .mao-label { color: var(--mao-muted); font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .06em; }
        .mao-value { margin-top: .35rem; font-size: clamp(1.55rem, 3vw, 2.2rem); line-height: 1; font-weight: 900; color: var(--mao-ink); }
        .mao-note { color: var(--mao-muted); font-size: .84rem; margin-top: .65rem; }
        .tone-green { --accent: var(--mao-green-bright); } .tone-blue { --accent: var(--mao-blue); } .tone-gold { --accent: var(--mao-gold); } .tone-red { --accent: var(--mao-red); }
        .mao-panel {
            border: 1px solid var(--mao-line);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(232,244,230,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            overflow: hidden;
        }
        .mao-panel-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1rem .75rem; border-bottom: 1px solid rgba(153,185,160,.58); background: rgba(226,241,219,.58); }
        .mao-panel-title { font-size: 1rem; font-weight: 900; margin: 0; color: var(--mao-ink); }
        .mao-panel-sub { margin: .15rem 0 0; color: var(--mao-muted); font-size: .82rem; }
        .mao-panel-body { padding: 1rem; }
        .mao-list-item { display: flex; gap: .85rem; align-items: flex-start; padding: .85rem 0; }
        .mao-list-item + .mao-list-item { border-top: 1px solid rgba(153,185,160,.55); }
        .list-mark { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: #e2f1df; color: var(--mao-green); font-weight: 900; font-size: .76rem; flex: 0 0 auto; }
        .row-title { font-weight: 900; color: var(--mao-ink); line-height: 1.25; }
        .row-text { color: var(--mao-muted); font-size: .84rem; margin-top: .15rem; line-height: 1.45; }
        .row-meta { color: #7a8f82; font-size: .76rem; margin-top: .35rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .status-pill { display: inline-flex; align-items: center; border-radius: 999px; padding: .32rem .58rem; font-size: .72rem; font-weight: 900; background: #d8f3dc; color: #1f6f4a; }
        .status-pill.muted { background: #e8eef6; color: #516579; }
        .quick-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .85rem; }
        .quick-action { min-height: 108px; padding: 1rem; border-radius: 8px; border: 1px solid rgba(153,185,160,.72); background: linear-gradient(135deg, #edf7e7, #dfeee8); color: inherit; text-decoration: none; }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(31,143,85,.55); }
        .quick-action strong { display: block; color: var(--mao-ink); }
        .quick-action span { display: block; color: var(--mao-muted); font-size: .82rem; margin-top: .25rem; }
        .heat-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .risk-card { padding: 1rem; border-radius: 8px; border: 1px solid rgba(153,185,160,.72); background: linear-gradient(135deg, #edf7e7, #e3f1e7); }
        .risk-card.severe, .risk-card.high { border-color: rgba(216,91,69,.45); background: linear-gradient(135deg, #fff3ee, #f5e7db); }
        .empty-soft { border: 1px dashed #aac7b0; background: linear-gradient(135deg, #edf7e7, #e2f1df); border-radius: 8px; padding: 1.5rem; text-align: center; }
        @media (max-width: 1199.98px) { .mao-stat-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } .quick-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .mao-stat-grid, .quick-grid, .heat-grid { grid-template-columns: 1fr; } .mao-panel-header { align-items: flex-start; flex-direction: column; } }
    </style>

    @php
        $latestSeason = $latestClimate?->season ?? 'N/A';
        $avgYield = $totalRiceProductions > 0 ? \App\Models\RiceProduction::query()->avg('yield_per_hectare') : 0;
    @endphp

    <div class="mao-console">
        <section class="mao-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="mao-eyebrow mb-2">Municipal Agriculture Office</div>
                    <h1 class="h2 fw-bold mb-2">Climate-informed <em>agricultural monitoring</em></h1>
                    <p class="mb-0">Monitor farmer records, climate observations, rice production performance, barangay risk records, advisories, announcements, and reports for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="mao-chip"><span class="mao-pulse"></span> Season: {{ $latestSeason }}</span>
                    <a class="btn btn-light fw-bold" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('reports.index') }}">Open Reports</a>
                </div>
            </div>
        </section>

        <section class="mao-stat-grid">
            <a class="mao-card tone-green text-decoration-none" href="{{ route('farmer-profiles.index') }}"><div class="mao-card-body"><div class="mao-label">Farmer Profiles</div><div class="mao-value">{{ number_format($profileCount) }}</div><div class="mao-note">Registered farm records</div></div></a>
            <a class="mao-card tone-blue text-decoration-none" href="{{ route('climate-records.index') }}"><div class="mao-card-body"><div class="mao-label">Climate Records</div><div class="mao-value">{{ number_format($totalClimateRecords) }}</div><div class="mao-note">Latest: {{ $latestClimate?->record_date?->format('M d, Y') ?? 'N/A' }}</div></div></a>
            <a class="mao-card tone-gold text-decoration-none" href="{{ route('rice-productions.index') }}"><div class="mao-card-body"><div class="mao-label">Rice Production</div><div class="mao-value">{{ number_format($riceProductionTotal, 1) }}</div><div class="mao-note">{{ number_format($riceAreaTotal, 1) }} hectares recorded</div></div></a>
            <a class="mao-card tone-red text-decoration-none" href="{{ route('heatmap-areas.index') }}"><div class="mao-card-body"><div class="mao-label">Heat Map Risks</div><div class="mao-value">{{ number_format($heatMapCount) }}</div><div class="mao-note">Barangay climate risk records</div></div></a>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Climate Monitoring Summary</h2><p class="mao-panel-sub">Recent rainfall, temperature, humidity, and wind entries.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('climate-records.index') }}">Manage Climate</a></div>
                    <div class="mao-panel-body">
                        @forelse($recentClimateRecords as $record)
                            <div class="mao-list-item">
                                <div class="list-mark">CLI</div>
                                <div class="flex-grow-1"><div class="row-title">{{ $record->record_date?->format('M d, Y') }} | {{ $record->season }} Season</div><div class="row-text">Rainfall {{ number_format($record->rainfall, 1) }} mm, temperature {{ number_format($record->temperature, 1) }} C, humidity {{ number_format($record->humidity, 1) }}%, wind {{ number_format($record->wind_speed, 1) }} km/h.</div><div class="row-meta">Source: {{ $record->source }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No climate records yet</strong><div class="small text-muted mt-1">Add climate records to populate this monitoring list.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Operational Tasks</h2><p class="mao-panel-sub">Common MAO actions for daily work.</p></div></div>
                    <div class="mao-panel-body">
                        <div class="quick-grid" style="grid-template-columns:1fr;">
                            <a class="quick-action" href="{{ route('rice-productions.create') }}"><strong>Validate Production Records</strong><span>Add or update barangay rice production data.</span></a>
                            <a class="quick-action" href="{{ route('notifications.create') }}"><strong>Send Farmer Notification</strong><span>Send advisories, announcements, or warnings.</span></a>
                            <a class="quick-action" href="{{ route('reports.index') }}"><strong>Generate Weekly Report</strong><span>Prepare climate and production summaries.</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Rice Production Monitoring</h2><p class="mao-panel-sub">Recent barangay production records and yield per hectare.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('rice-productions.index') }}">Open Records</a></div>
                    <div class="mao-panel-body">
                        @forelse($recentRiceProductions as $production)
                            <div class="mao-list-item">
                                <div class="list-mark">RIC</div>
                                <div class="flex-grow-1"><div class="row-title">{{ $production->barangay }} | {{ $production->season }} {{ $production->year }}</div><div class="row-text">{{ number_format($production->area_hectares, 2) }} ha, {{ number_format($production->yield_per_hectare, 2) }} yield/ha, total {{ number_format($production->total_production, 2) }}.</div><div class="row-meta">{{ $production->irrigation_type }}</div></div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No production records yet</strong><div class="small text-muted mt-1">Rice production entries will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-5">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Barangay Heat Map Overview</h2><p class="mao-panel-sub">No GIS integration yet; these are managed risk records.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('heatmap-areas.index') }}">Manage Risks</a></div>
                    <div class="mao-panel-body"><div class="heat-grid">
                        @forelse($latestHeatmapAreas as $area)
                            <div class="risk-card {{ strtolower($area->risk_level) }}"><div class="d-flex justify-content-between gap-2 mb-2"><strong>{{ $area->barangay }}</strong><span class="status-pill {{ in_array($area->risk_level, ['High','Severe']) ? 'muted' : '' }}">{{ $area->risk_level }}</span></div><div class="row-text">{{ $area->risk_type }} risk</div><div class="row-meta">{{ str($area->description)->limit(70) }}</div></div>
                        @empty
                            <div class="empty-soft"><strong>No risk records</strong><div class="small text-muted mt-1">Barangay heat map records will appear here.</div></div>
                        @endforelse
                    </div></div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Advisory Management</h2><p class="mao-panel-sub">Latest advisories for rice farmers.</p></div><a class="btn btn-sm btn-primary" href="{{ route('planting-advisories.create') }}">New Advisory</a></div>
                    <div class="mao-panel-body">
                        @forelse($latestAdvisories as $advisory)
                            <div class="mao-list-item"><div class="list-mark">ADV</div><div><div class="row-title">{{ $advisory->title }}</div><div class="row-text">{{ str($advisory->content)->limit(95) }}</div><div class="row-meta">{{ $advisory->type }} | {{ $advisory->status }} | {{ $advisory->target_barangay ?: 'All barangays' }}</div></div></div>
                        @empty
                            <div class="empty-soft"><strong>No advisories yet</strong><div class="small text-muted mt-1">Create advisories for farmers from this module.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="mao-panel h-100">
                    <div class="mao-panel-header"><div><h2 class="mao-panel-title">Announcements</h2><p class="mao-panel-sub">Latest municipal updates.</p></div><a class="btn btn-sm btn-primary" href="{{ route('announcements.create') }}">New Announcement</a></div>
                    <div class="mao-panel-body">
                        @forelse($latestAnnouncements as $announcement)
                            <div class="mao-list-item"><div class="list-mark">ANN</div><div><div class="row-title">{{ $announcement->title }}</div><div class="row-text">{{ str($announcement->content)->limit(95) }}</div><div class="row-meta">{{ $announcement->category }} | {{ $announcement->status }}</div></div></div>
                        @empty
                            <div class="empty-soft"><strong>No announcements yet</strong><div class="small text-muted mt-1">Published announcements will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <div class="mao-panel">
            <div class="mao-panel-header"><div><h2 class="mao-panel-title">Reports & Analytics</h2><p class="mao-panel-sub">Generate summaries for climate records, rice production, farmer registration, advisories, and announcements.</p></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">Reports Center</a></div>
            <div class="mao-panel-body">
                <div class="quick-grid mb-3">
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Climate Records Report']) }}"><strong>Climate Report</strong><span>Filter by dates, barangay, and season.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Rice Production Report']) }}"><strong>Rice Production Report</strong><span>Review year, season, area, and production totals.</span></a>
                    <a class="quick-action" href="{{ route('reports.index', ['report_type' => 'Farmer Registration Report']) }}"><strong>Farmer Report</strong><span>Summarize farmer account and profile records.</span></a>
                </div>
                @forelse($latestReports as $report)
                    <div class="mao-list-item"><div class="list-mark">RPT</div><div class="flex-grow-1"><div class="row-title">{{ $report->title }}</div><div class="row-text">{{ $report->report_type }} generated by {{ $report->generatedBy?->name ?? 'System' }}.</div><div class="row-meta">{{ $report->created_at?->format('M d, Y') }}</div></div><a class="btn btn-sm btn-outline-primary" href="{{ route('reports.show', $report) }}">Open</a></div>
                @empty
                    <div class="empty-soft"><strong>No report history yet</strong><div class="small text-muted mt-1">Generated reports will appear here.</div></div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>