<x-app-layout>
    @include('advisories._analytics-status')
    <style>
        .advisory-safety-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid rgba(255,209,102,.48);
            border-radius: 14px;
            background:
                linear-gradient(135deg, rgba(197,139,42,.22) 0%, rgba(18,43,32,.98) 42%, rgba(7,25,18,.99) 100%),
                #0d1f18;
            color: #fff;
            padding: 1rem;
            box-shadow: 0 4px 20px rgba(13,31,24,.12);
        }
        .advisory-safety-card::before {
            content: "";
            position: absolute;
            inset: 0;
            pointer-events: none;
            background:
                radial-gradient(circle at 8% 35%, rgba(255,232,163,.16), transparent 18rem),
                radial-gradient(circle at 92% 85%, rgba(82,183,136,.22), transparent 16rem);
        }
        .advisory-safety-card > * { position: relative; z-index: 1; }
        .advisory-safety-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #74c69d;
            font-family: 'DM Mono', monospace;
            font-size: .875rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .advisory-safety-eyebrow::before {
            content: none;
        }
        .advisory-safety-card h2 {
            color: #fff;
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.1rem;
            margin: .75rem 0 .35rem;
        }
        .advisory-safety-card p {
            color: rgba(255,255,255,.8);
            line-height: 1.55;
            margin: 0;
        }
        .advisory-safety-status {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .42rem .7rem;
            background: rgba(82,183,136,.18);
            color: #74c69d;
            font-size: .875rem;
            font-weight: 900;
        }
        .advisory-safety-status.needs-help {
            background: rgba(255,209,102,.16);
            color: #ffe8a3;
        }
        .advisory-safety-note {
            min-height: 74px;
            border: 1px solid rgba(255,255,255,.16);
            border-radius: 10px;
            background: rgba(255,255,255,.06);
            color: #fff;
            padding: .7rem .85rem;
        }
        .advisory-safety-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .6rem;
        }
        @media (max-width: 767.98px) {
            .row.g-3.mb-4 { margin-bottom: 1rem !important; }
            .advisory-safety-card { padding: .85rem; }
            .advisory-safety-card h2 { font-size: 1rem; margin: .6rem 0 .3rem; }
            .advisory-safety-note { min-height: 60px; padding: .6rem .7rem; }
            .advisory-safety-actions { gap: .45rem; }
            .advisory-safety-actions .btn { flex: 1 1 auto; }
            .empty-state.p-5 { padding: 2rem 1rem !important; }
        }
    </style>
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Forecast-Based Guidance</div>
                <h1 class="h2 fw-bold mb-2">Farming Advisories</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">View PAGASA online advisories for Lian, Batangas plus local farm guidance based on current forecast conditions.</p>
            </div>
            @if($canManage)
                <div class="d-flex flex-wrap gap-2 action-cluster">
                    <a class="btn btn-light" href="{{ route('management.advisories.index') }}">Review Advisories</a>
                    <a class="btn btn-outline-light" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                    <form method="POST" action="{{ route('planting-advisories.refresh-pagasa') }}" data-loading="true">
                        @csrf
                        <button class="btn btn-outline-light" type="submit" data-loading-text="Refreshing...">Refresh PAGASA</button>
                    </form>
                </div>
            @else
                <form method="POST" action="{{ route('planting-advisories.refresh-pagasa') }}" data-loading="true" class="action-cluster">
                    @csrf
                    <button class="btn btn-light" type="submit" data-loading-text="Refreshing...">Refresh PAGASA</button>
                </form>
            @endif
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Latest Weather Bureau Update</div>
                    <div class="h5 fw-bold mt-2 mb-1">{{ $latestPagasaAdvisory?->created_at?->shortDateTime('F d, Y,') ?? 'No PAGASA update for Lian yet' }}</div>
                    <div class="text-muted small">Source: {{ $latestPagasaAdvisory?->plainSourceLabel() ?: 'PAGASA' }}, for Lian, Batangas</div>
                    @isset($pagasaSummary)
                        <details class="text-muted small mt-2">
                            <summary>View Details</summary>
                            PAGASA pages checked: {{ number_format($pagasaSummary['sources_checked'] ?? 0) }};
                            new updates: {{ number_format($pagasaSummary['advisories_created'] ?? 0) }};
                            none for Lian: {{ number_format($pagasaSummary['sources_without_lian_batangas_match'] ?? 0) }}
                        </details>
                    @endisset
                    @if($lastWeather)
                        <div class="text-muted small mt-2">Weather last updated: {{ $lastWeather->fetched_at?->shortDateTime('M d,') }}</div>
                    @endif
                    @if($latestPagasaAdvisory)
                        <span class="badge text-bg-success mt-2">{{ $latestPagasaAdvisory->plainSourceLabel() }} Active</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Active Advisories</div>
                    <div class="h3 fw-bold mt-2 mb-1">{{ number_format($activeCount) }}</div>
                    <div class="text-muted small">{{ number_format($pagasaAdvisoryCount) }} of these come from PAGASA.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            @if($activeTyphoonSafetyEvent)
                <div class="advisory-safety-card" aria-labelledby="typhoonSafetyTitle">
                    <div class="advisory-safety-eyebrow">Typhoon safety check</div>
                    <h2 id="typhoonSafetyTitle">Are you safe right now?</h2>
                    <p>{{ $activeTyphoonSafetyEvent['title'] }} | {{ $activeTyphoonSafetyEvent['severity'] }}</p>
                    @if($typhoonSafetyResponse)
                        <div class="mt-3">
                            <span class="advisory-safety-status {{ $typhoonSafetyResponse->status === 'needs_help' ? 'needs-help' : '' }}">
                                Current response: {{ $typhoonSafetyResponse->statusLabel() }}
                            </span>
                        </div>
                    @endif
                    <form class="d-grid gap-2 mt-3" method="POST" action="{{ route('typhoon-safety.store') }}" data-loading="true">
                        @csrf
                        <input type="hidden" name="event_key" value="{{ $activeTyphoonSafetyEvent['key'] }}">
                        <textarea class="advisory-safety-note" name="note" rows="2" placeholder="Optional note for MAO.">{{ old('note', $typhoonSafetyResponse?->note) }}</textarea>
                        <div class="advisory-safety-actions">
                            <button class="btn btn-primary fw-bold rounded-pill px-4" type="submit" name="status" value="safe">I am safe</button>
                            <button class="btn btn-danger fw-bold rounded-pill px-4" type="submit" name="status" value="needs_help">I need help</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="card no-lift h-100">
                    <div class="card-body">
                        <div class="stat-label">Safety Reminder</div>
                        <div class="text-muted mt-2">Generated advisories support planning. Follow official PAGASA online advisories and LGU warnings during severe weather.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @php
        $activeFilterCount = collect(['search', 'advisory_type', 'status', 'severity', 'target_barangay'])
            ->filter(fn ($field) => request()->filled($field))
            ->count();
    @endphp
    <details class="ic-collapse" @if($activeFilterCount) data-force-open="true" @endif>
        <summary>
            <span class="ic-collapse-title">
                Filters
                @if($activeFilterCount)
                    <span class="ic-collapse-count">{{ $activeFilterCount }}</span>
                @endif
            </span>
        </summary>
        <div class="card filter-panel no-lift mb-4">
            <div class="card-body">
                <form class="row g-3 align-items-end" method="GET">
                    <div class="col-lg-3">
                        <label class="form-label">Search</label>
                        <input class="form-control form-control-lg" name="search" value="{{ request('search') }}" placeholder="Search advisories">
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label">Type</label>
                        <select class="form-select form-select-lg" name="advisory_type">
                            <option value="">All</option>
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(request('advisory_type') === $type)>{{ str($type)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label">Status</label>
                        <select class="form-select form-select-lg" name="status">
                            <option value="">All</option>
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label">Severity</label>
                        <select class="form-select form-select-lg" name="severity">
                            <option value="">All</option>
                            @foreach($severities as $severity)
                                <option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ str($severity)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-sm-6 col-lg-2">
                        <label class="form-label">Target Barangay</label>
                        <select class="form-select form-select-lg" name="target_barangay">
                            <option value="">All Barangays</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay }}" @selected(request('target_barangay') === $barangay)>{{ $barangay }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-auto d-flex gap-2">
                        <button class="btn btn-outline-primary btn-lg" type="submit">Apply</button>
                        <a class="btn btn-outline-secondary btn-lg" href="{{ route('planting-advisories.index') }}">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </details>

    @if($advisories->count())
        <div class="card no-lift">
            <div class="table-responsive ic-table-hide-mobile">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Applies To</th>
                            <th>Severity</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($advisories as $advisory)
                            <tr>
                                <td><div class="fw-bold">{{ $advisory->title }}</div><div class="small text-muted">{{ $advisory->displaySummary() }}</div><div class="small text-muted">{{ $advisory->horizonLabel() }} &middot; {{ $advisory->plainSourceLabel() }}</div></td>
                                <td><span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span></td>
                                <td>{{ $advisory->targetLabel() }}</td>
                                <td><span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span></td>
                                <td>{{ $advisory->valid_until?->shortDateTime() ?? 'Open' }}</td>
                                <td><span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('planting-advisories.show', $advisory) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body ic-table-cards">
                @foreach($advisories as $advisory)
                    <div class="ic-table-card">
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Title</div><div class="ic-table-card-value fw-bold">{{ $advisory->title }}<div class="small text-muted fw-normal">{{ $advisory->displaySummary() }}</div></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Type</div><div class="ic-table-card-value"><span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Period</div><div class="ic-table-card-value"><span class="badge text-bg-light">{{ $advisory->horizonLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Target</div><div class="ic-table-card-value">{{ $advisory->targetLabel() }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Severity</div><div class="ic-table-card-value"><span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Valid Until</div><div class="ic-table-card-value">{{ $advisory->valid_until?->format('M d, Y g:i A') ?? 'Open' }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Status</div><div class="ic-table-card-value"><span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span></div></div>
                        <div class="ic-table-card-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('planting-advisories.show', $advisory) }}">View</a>
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-body border-top d-flex justify-content-end">{{ $advisories->links() }}</div>
        </div>
    @else
        <div class="empty-state text-center p-5">
            <div class="h5 mb-2">No active advisories are available for your location.</div>
            <p class="text-muted mb-0">New advice will appear here when the weather calls for it.</p>
        </div>
    @endif
</x-app-layout>
