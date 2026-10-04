<x-app-layout>
    @include('advisories._analytics-status')
    <section class="page-hero">
        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
            <div>
                <div class="eyebrow mb-2">MAO/Admin Workspace</div>
                <h1 class="h2 fw-bold mb-2">Advisory Management</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Review advisories, update the weather, and publish guidance for farmers.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 action-cluster">
                <form method="POST" action="{{ route('management.advisories.refresh-weather') }}" data-loading="true" data-ic-confirm="Get the latest weather forecast now?">
                    @csrf
                    <button class="btn btn-light" data-loading-text="Refreshing..." type="submit">Update Weather</button>
                </form>
                <form method="POST" action="{{ route('management.advisories.regenerate') }}" data-loading="true" data-ic-confirm="Get the latest Lian, Batangas weather and create new advisories now?">
                    @csrf
                    <button class="btn btn-outline-light" data-loading-text="Generating..." type="submit">Create New Advisories</button>
                </form>
            </div>
        </div>
    </section>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3"><div class="card no-lift h-100"><div class="card-body"><div class="stat-label">Active Advisories</div><div class="stat-value">{{ $activeCount }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card no-lift h-100"><div class="card-body"><div class="stat-label">Pending Review</div><div class="stat-value">{{ $pendingCount }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card no-lift h-100"><div class="card-body"><div class="stat-label">High/Critical</div><div class="stat-value">{{ $highRiskCount }}</div></div></div></div>
        <div class="col-md-6 col-xl-3"><div class="card no-lift h-100"><div class="card-body"><div class="stat-label">Weather Last Updated</div><div class="h6 fw-bold mt-2">{{ $lastWeather?->fetched_at?->shortDateTime() ?? 'None' }}</div>@if($lastWeather)<span class="badge text-bg-{{ $lastWeather->freshnessLabel() === 'fresh' ? 'success' : ($lastWeather->freshnessLabel() === 'delayed' ? 'warning' : 'danger') }}">{{ str($lastWeather->freshnessLabel())->headline() }}</span>@endif</div></div></div>
    </div>

    @php
        $activeFilterCount = collect(['search', 'advisory_type', 'status', 'severity'])
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
                    <div class="col-lg-3"><label class="form-label">Search</label><input class="form-control form-control-lg" name="search" value="{{ request('search') }}"></div>
                    <div class="col-sm-6 col-lg-2"><label class="form-label">Type</label><select class="form-select form-select-lg" name="advisory_type"><option value="">All</option>@foreach($types as $type)<option value="{{ $type }}" @selected(request('advisory_type') === $type)>{{ str($type)->headline() }}</option>@endforeach</select></div>
                    <div class="col-sm-6 col-lg-2"><label class="form-label">Status</label><select class="form-select form-select-lg" name="status"><option value="">All</option>@foreach($statuses as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>@endforeach</select></div>
                    <div class="col-sm-6 col-lg-2"><label class="form-label">Severity</label><select class="form-select form-select-lg" name="severity"><option value="">All</option>@foreach($severities as $severity)<option value="{{ $severity }}" @selected(request('severity') === $severity)>{{ str($severity)->headline() }}</option>@endforeach</select></div>
                    <div class="col-lg-auto d-flex gap-2"><button class="btn btn-outline-primary btn-lg">Apply</button><a class="btn btn-outline-secondary btn-lg" href="{{ route('management.advisories.index') }}">Reset</a></div>
                </form>
            </div>
        </div>
    </details>

    <div class="card no-lift">
        @if($advisories->count())
            <div class="table-responsive ic-table-hide-mobile">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th>Title</th><th>Type</th><th>Period</th><th>Severity</th><th>Applies To</th><th>Status</th><th>Valid Until</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                        @foreach($advisories as $advisory)
                            <tr>
                                <td><div class="fw-bold">{{ $advisory->title }}</div><div class="small text-muted">{{ $advisory->displaySummary() }}</div></td>
                                <td><span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span></td>
                                <td><span class="badge text-bg-light">{{ $advisory->horizonLabel() }}</span></td>
                                <td><span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span></td>
                                <td>{{ $advisory->targetLabel() }}</td>
                                <td><span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span></td>
                                <td>{{ $advisory->valid_until?->shortDateTime() }}</td>
                                <td class="text-end text-nowrap">
                                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('planting-advisories.show', $advisory) }}" data-ic-view aria-haspopup="dialog">View</a>
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('planting-advisories.edit', $advisory) }}">Edit</a>
                                    @if($advisory->status === 'pending_review')
                                        <form class="d-inline" method="POST" action="{{ route('management.advisories.approve', $advisory) }}" data-loading="true" data-ic-confirm="Publish this advisory?">@csrf<button class="btn btn-sm btn-outline-success" data-loading-text="Publishing...">Approve</button></form>
                                        <form class="d-inline" method="POST" action="{{ route('management.advisories.reject', $advisory) }}" data-loading="true" onsubmit="const reason = prompt('Reason for rejection?'); if (!reason) return false; this.querySelector('[name=rejection_reason]').value = reason; return true;">@csrf<input type="hidden" name="rejection_reason"><button class="btn btn-sm btn-outline-danger" data-loading-text="Rejecting...">Reject</button></form>
                                    @endif
                                    @if(! in_array($advisory->status, ['archived', 'expired'], true))
                                        <form class="d-inline" method="POST" action="{{ route('management.advisories.archive', $advisory) }}" data-loading="true" data-ic-confirm="Archive this advisory?">@csrf<button class="btn btn-sm btn-outline-dark" data-loading-text="Archiving...">Archive</button></form>
                                    @endif
                                </td>
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
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Window</div><div class="ic-table-card-value"><span class="badge text-bg-light">{{ $advisory->horizonLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Severity</div><div class="ic-table-card-value"><span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Target</div><div class="ic-table-card-value">{{ $advisory->targetLabel() }}</div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Status</div><div class="ic-table-card-value"><span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span></div></div>
                        <div class="ic-table-card-row"><div class="ic-table-card-label">Valid Until</div><div class="ic-table-card-value">{{ $advisory->valid_until?->format('M d, Y g:i A') }}</div></div>
                        <div class="ic-table-card-actions">
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('planting-advisories.show', $advisory) }}" data-ic-view aria-haspopup="dialog">View</a>
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('planting-advisories.edit', $advisory) }}">Edit</a>
                            @if($advisory->status === 'pending_review')
                                <form method="POST" action="{{ route('management.advisories.approve', $advisory) }}" data-loading="true" data-ic-confirm="Publish this advisory?">@csrf<button class="btn btn-sm btn-outline-success" data-loading-text="Publishing...">Approve</button></form>
                                <form method="POST" action="{{ route('management.advisories.reject', $advisory) }}" data-loading="true" onsubmit="const reason = prompt('Reason for rejection?'); if (!reason) return false; this.querySelector('[name=rejection_reason]').value = reason; return true;">@csrf<input type="hidden" name="rejection_reason"><button class="btn btn-sm btn-outline-danger" data-loading-text="Rejecting...">Reject</button></form>
                            @endif
                            @if(! in_array($advisory->status, ['archived', 'expired'], true))
                                <form method="POST" action="{{ route('management.advisories.archive', $advisory) }}" data-loading="true" data-ic-confirm="Archive this advisory?">@csrf<button class="btn btn-sm btn-outline-dark" data-loading-text="Archiving...">Archive</button></form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="card-body border-top d-flex justify-content-end">{{ $advisories->links() }}</div>
        @else
            <div class="card-body"><div class="empty-state text-center p-5"><div class="h5">No advisories match the filters.</div><p class="text-muted mb-0">Update the weather or create new advisories when the forecast changes.</p></div></div>
        @endif
    </div>
</x-app-layout>
