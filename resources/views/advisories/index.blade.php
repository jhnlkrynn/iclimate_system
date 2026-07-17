<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">Forecast-Based Guidance</div>
                <h1 class="h2 fw-bold mb-2">Agricultural Advisories</h1>
                <p class="mb-0 text-white-50">View climate, planting, harvesting, and irrigation guidance based on current forecast conditions.</p>
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
                    <div class="stat-label">Last Weather Update</div>
                    <div class="h5 fw-bold mt-2 mb-1">{{ $lastWeather?->fetched_at?->format('F d, Y, g:i A') ?? 'No weather data yet' }}</div>
                    <div class="text-muted small">Forecast source: Open-Meteo</div>
                    @if($lastWeather)
                        <span class="badge text-bg-{{ $lastWeather->freshnessLabel() === 'fresh' ? 'success' : ($lastWeather->freshnessLabel() === 'delayed' ? 'warning' : 'danger') }} mt-2">{{ str($lastWeather->freshnessLabel())->headline() }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Advisories Generated</div>
                    <div class="h3 fw-bold mt-2 mb-1">{{ number_format($activeCount) }}</div>
                    <div class="text-muted small">Active published advisories available now.</div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card no-lift h-100">
                <div class="card-body">
                    <div class="stat-label">Safety Reminder</div>
                    <div class="text-muted mt-2">Generated advisories support planning. Follow official PAGASA and LGU warnings during severe weather.</div>
                </div>
            </div>
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
</x-app-layout>
