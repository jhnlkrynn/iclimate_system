<x-app-layout>
    <style>
        .report-shell { display: grid; gap: 1rem; }
        .report-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .report-stat { border: 1px solid #d4edda; border-radius: 18px; background: #fff; padding: 1rem; }
        .report-stat-label { color: #5a7a64; font-size: .75rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
        .report-stat-value { color: #0d1f18; font-size: 1.8rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
        .report-action-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .report-live-strip { display: grid; grid-template-columns: 1.4fr repeat(5, minmax(0, 1fr)); gap: .75rem; }
        .report-live-item { border: 1px solid var(--ic-border); border-radius: 14px; background: var(--ic-panel); padding: .85rem; min-width: 0; }
        .report-live-item strong { display: block; color: var(--ic-ink); font-size: 1.25rem; line-height: 1.1; }
        .report-live-item span { display: block; color: var(--ic-muted); font-size: .72rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; margin-top: .2rem; }
        .report-empty-state { border: 1px dashed rgba(116,198,157,.34); border-radius: 18px; background: var(--ic-panel); color: var(--ic-ink); }
        .report-empty-state .h5 { color: var(--ic-ink); }
        .report-empty-state .text-muted { color: var(--ic-muted) !important; }
        .report-page .report-white-action {
            color: var(--ic-ink) !important;
            border-color: var(--ic-border) !important;
            background: rgba(31,42,36,.04) !important;
        }
        .report-page .report-white-action:hover,
        .report-page .report-white-action:focus {
            color: var(--ic-ink) !important;
            border-color: var(--ic-green-500) !important;
            background: var(--ic-green-50) !important;
            box-shadow: 0 .65rem 1.2rem rgba(13,31,24,.08);
        }
        .report-table th { white-space: nowrap; }
        .report-mobile-list { display: none; gap: .75rem; }
        .report-mobile-card { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .9rem; }
        .report-mobile-row { display: grid; grid-template-columns: minmax(110px, .42fr) minmax(0, 1fr); gap: .75rem; padding: .45rem 0; }
        .report-mobile-row + .report-mobile-row { border-top: 1px solid #eef7ec; }
        .report-mobile-label { color: #5a7a64; font-size: .74rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .report-mobile-value { color: #0d1f18; font-weight: 700; overflow-wrap: anywhere; }
        .history-card-list { display: none; gap: .75rem; }
        @media (max-width: 991.98px) {
            .report-summary-grid { grid-template-columns: 1fr; }
            .report-live-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .report-live-item:first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 767.98px) {
            .report-action-bar, .report-action-bar .btn { width: 100%; }
            .report-results-table, .report-history-table { display: none; }
            .report-mobile-list, .history-card-list { display: grid; }
            .report-mobile-row { grid-template-columns: 1fr; gap: .2rem; }
            .report-live-strip { grid-template-columns: 1fr; }
        }
    </style>

    <div class="report-page">
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">Analytics Workspace</div>
            <h1 class="h2 fw-bold mb-2">Reports</h1>
            <p class="mb-0" style="color: var(--ic-ink-mid);">Generate filtered reports from current iClimate records. Dummy report history is excluded.</p>
        </div>
    </section>

    <div class="report-shell">
    <div class="card no-lift">
        <div class="card-body p-3">
            <div class="report-live-strip">
                <div class="report-live-item">
                    <strong>Live app data</strong>
                    <span>Refreshed {{ $sourceStats['last_refreshed']->shortDateTime('M d, Y') }}</span>
                </div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['climate_records']) }}</strong><span>Climate</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['rice_production']) }}</strong><span>Rice rows</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['farmers']) }}</strong><span>Farmers</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['advisories']) }}</strong><span>Advisories</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['community_posts']) }}</strong><span>Posts</span></div>
            </div>
        </div>
    </div>

    <div class="card filter-panel no-lift">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('reports.generate') }}" class="row g-3" data-loading="true">
                @csrf
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Report Type</label>
                    <select class="form-select" name="report_type" required>
                        @foreach ($reportTypes as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['report_type'] ?? '') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2"><label class="form-label fw-semibold">Date From</label><input class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label fw-semibold">Date To</label><input class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
                <div class="col-sm-6 col-lg-2"><label class="form-label fw-semibold">Year</label><input class="form-control" type="number" name="year" value="{{ $filters['year'] ?? '' }}" min="1900" max="2100"></div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label fw-semibold">Season</label>
                    <select class="form-select" name="season"><option value="">All</option>@foreach ($seasons as $season)<option value="{{ $season }}" @selected(($filters['season'] ?? '') === $season)>{{ $season }}</option>@endforeach</select>
                </div>
                <div class="col-lg-4">
                    <label class="form-label fw-semibold">Barangay</label>
                    <select class="form-select" name="barangay"><option value="">All Barangays</option>@foreach ($barangays as $barangay)<option value="{{ $barangay }}" @selected(($filters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>@endforeach</select>
                </div>
                <div class="col-lg-8 report-action-bar align-self-end">
                    <button class="btn btn-primary" type="submit" data-loading-text="Generating...">Generate Report</button>
                    @if ($selected)
                        <button class="btn report-white-action" formaction="{{ route('reports.print') }}" formmethod="GET" formtarget="_blank" type="submit" data-loading-text="Opening...">Print Report</button>
                        <button class="btn report-white-action" formaction="{{ route('reports.export') }}" formmethod="GET" type="submit" data-loading-text="Exporting...">Export CSV</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($selected)
        <div class="report-summary-grid">
            <div class="report-stat"><div class="report-stat-label">Report Type</div><div class="h5 fw-bold mb-0 mt-2">{{ $selected }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Rows Found</div><div class="report-stat-value">{{ number_format($rows->count()) }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Generated</div><div class="h5 fw-bold mb-0 mt-2">{{ now()->shortDateTime('M d, Y') }}</div></div>
        </div>

        <div class="card no-lift">
            <div class="card-header border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                    <div><h2 class="h5 mb-1">{{ $selected }} Results</h2><div class="small text-muted">Review the generated rows below, then download the shown report as CSV.</div></div>
                    <div class="report-result-actions d-flex flex-wrap gap-2 align-items-start">
                        <span class="badge text-bg-light border align-self-start">{{ number_format($rows->count()) }} row(s)</span>
                        <form method="GET" action="{{ route('reports.print') }}" target="_blank">
                            @foreach ($filters as $key => $value)
                                @if (! blank($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <button class="btn btn-sm fw-bold report-white-action" type="submit" data-loading-text="Opening...">Print Report</button>
                        </form>
                        <form method="GET" action="{{ route('reports.export') }}">
                            @foreach ($filters as $key => $value)
                                @if (! blank($value))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <button class="btn btn-sm fw-bold report-white-action" type="submit" data-loading-text="Exporting...">Download CSV</button>
                        </form>
                    </div>
                </div>
            </div>
            @if ($rows->count())
                <div class="table-responsive report-results-table">
                    <table class="table table-hover report-table mb-0">
                        <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>@foreach ($rows as $row)<tr>@foreach ($columns as $field => $label)<td>{{ data_get($row, $field) }}</td>@endforeach</tr>@endforeach</tbody>
                    </table>
                </div>
                <div class="card-body report-mobile-list">
                    @foreach ($rows as $row)
                        <div class="report-mobile-card">
                            @foreach ($columns as $field => $label)
                                <div class="report-mobile-row">
                                    <div class="report-mobile-label">{{ $label }}</div>
                                    <div class="report-mobile-value">{{ data_get($row, $field) }}</div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @else
                <div class="card-body">
                    <div class="report-empty-state text-center p-5">
                        <div class="h5 mb-2">No report records found</div>
                        <div class="text-muted">No current iClimate records match the selected filters. Try widening the date range or choosing All Barangays.</div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="card no-lift">
        <div class="card-header border-0 py-3"><h2 class="h5 mb-1">Report History</h2><div class="small text-muted">Only real reports generated from current iClimate records are shown.</div></div>
        <div class="table-responsive report-history-table">
            <table class="table table-hover report-table mb-0">
                <thead><tr><th>Type</th><th>Title</th><th>Generated By</th><th>Date</th><th>Rows</th></tr></thead>
                <tbody>
                    @forelse ($history as $report)
                        <tr><td>{{ $report->report_type }}</td><td>{{ $report->title }}</td><td>{{ $report->generatedBy?->name }}</td><td>{{ $report->created_at }}</td><td>{{ data_get($report->payload, 'row_count', '-') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No report history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body history-card-list">
            @forelse ($history as $report)
                <div class="report-mobile-card">
                    <div class="fw-bold">{{ $report->title }}</div>
                    <div class="small text-muted mb-2">{{ $report->report_type }}</div>
                    <div class="report-mobile-row"><div class="report-mobile-label">Generated By</div><div class="report-mobile-value">{{ $report->generatedBy?->name }}</div></div>
                    <div class="report-mobile-row"><div class="report-mobile-label">Date</div><div class="report-mobile-value">{{ $report->created_at }}</div></div>
                    <div class="report-mobile-row"><div class="report-mobile-label">Rows</div><div class="report-mobile-value">{{ data_get($report->payload, 'row_count', '-') }}</div></div>
                </div>
            @empty
                <div class="text-center text-muted py-4">No report history yet.</div>
            @endforelse
        </div>
        <div class="card-body border-top d-flex justify-content-end">{{ $history->links() }}</div>
    </div>
    </div>
    </div>
</x-app-layout>
