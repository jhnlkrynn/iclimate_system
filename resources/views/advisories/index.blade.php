<x-app-layout>
    @include('layouts.partials.dark-workspace')
    <style>
        .advisory-safety-card {
            position: relative;
            overflow: hidden;
            height: 100%;
            border: 1px solid rgba(255,209,102,.48);
            border-radius: 14px;
            background:
                linear-gradient(135deg, rgba(232,167,61,.24) 0%, rgba(18,43,32,.98) 42%, rgba(7,25,18,.99) 100%),
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
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }
        .advisory-safety-eyebrow::before {
            content: "";
            width: 20px;
            height: 1px;
            background: #74c69d;
        }
        .advisory-safety-card h2 {
            color: #fff;
            font-family: 'DM Serif Display', Georgia, serif;
            font-size: 1.1rem;
            margin: .75rem 0 .35rem;
        }
        .advisory-safety-card p {
            color: rgba(255,255,255,.68);
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
            font-size: .78rem;
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
    </style>
    <div class="dark-workspace">
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Forecast-Based Guidance</div>
                <h1 class="h2 fw-bold mb-2">Agricultural Advisories</h1>
                <p class="mb-0 text-white-50">View PAGASA online advisories for Lian, Batangas plus local farm guidance based on current forecast conditions.</p>
            </div>
            @if($canManage)
                <div class="d-flex flex-wrap gap-2 action-cluster">
                    <a class="btn btn-light" href="{{ route('management.advisories.index') }}">Review Workspace</a>
                    <a class="btn btn-outline-light" href="{{ route('planting-advisories.create') }}">Create Advisory</a>
                </div>
            @endif
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Latest PAGASA Online Advisory</div>
                    <div class="h5 fw-bold mt-2 mb-1">{{ $latestPagasaAdvisory?->created_at?->format('F d, Y, g:i A') ?? 'No PAGASA match yet' }}</div>
                    <div class="text-muted small">Official source: PAGASA, filtered for Lian/Batangas</div>
                    @if($lastWeather)
                        <div class="text-muted small mt-2">Forecast guidance updated: {{ $lastWeather->fetched_at?->format('M d, g:i A') }}</div>
                    @endif
                    @if($latestPagasaAdvisory)
                        <span class="badge text-bg-success mt-2">PAGASA Active</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Advisories Generated</div>
                    <div class="h3 fw-bold mt-2 mb-1">{{ number_format($activeCount) }}</div>
                    <div class="text-muted small">{{ number_format($pagasaAdvisoryCount) }} active advisory records are from PAGASA.</div>
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
                            <button class="btn btn-warning fw-bold rounded-pill px-4" type="submit" name="status" value="safe">I am safe</button>
                            <button class="btn btn-outline-light fw-bold rounded-pill px-4" type="submit" name="status" value="needs_help">I need help</button>
                        </div>
                    </form>
                </div>
            @else
                <div class="card no-lift h-100">
                    <div class="card-body">
                        <div class="stat-label">Safety Reminder</div>
                        <div class="text-muted mt-2">Generated advisories support planning. Follow official PAGASA and LGU warnings during severe weather.</div>
                    </div>
                </div>
            @endif
        </div>
    </div>

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

    @if($advisories->count())
        <div class="card no-lift">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Window</th>
                            <th>Target Barangay</th>
                            <th>Severity</th>
                            <th>Source</th>
                            <th>Valid Until</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($advisories as $advisory)
                            <tr>
                                <td><div class="fw-bold">{{ $advisory->title }}</div><div class="small text-muted">{{ $advisory->summary }}</div></td>
                                <td><span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span></td>
                                <td><span class="badge text-bg-light">{{ $advisory->horizonLabel() }}</span></td>
                                <td>{{ $advisory->targetLabel() }}</td>
                                <td><span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span></td>
                                <td>{{ $advisory->source ?: 'Open-Meteo + iClimate Rules' }}</td>
                                <td>{{ $advisory->valid_until?->format('M d, Y g:i A') ?? 'Open' }}</td>
                                <td><span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span></td>
                                <td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="{{ route('planting-advisories.show', $advisory) }}">View</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body border-top d-flex justify-content-end">{{ $advisories->links() }}</div>
        </div>
    @else
        <div class="empty-state text-center p-5">
            <div class="h5 mb-2">No active advisories are available for your location.</div>
            <p class="text-muted mb-0">The system will display new guidance when relevant weather conditions are detected.</p>
        </div>
    @endif
    </div>
</x-app-layout>
