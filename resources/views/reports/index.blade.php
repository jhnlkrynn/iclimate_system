<x-app-layout>
    <style>
        .report-shell { display: grid; gap: 1rem; }
        .report-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .report-stat { border: 1px solid #d4edda; border-radius: 18px; background: #fff; padding: 1rem; }
        .report-stat-label { color: #5a7a64; font-size: .75rem; font-weight: 900; text-transform: uppercase; letter-spacing: .05em; }
        .report-stat-value { color: #0d1f18; font-size: 1.8rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
        .report-action-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .report-table th { white-space: nowrap; }
        .report-mobile-list { display: none; gap: .75rem; }
        .report-mobile-card { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .9rem; }
        .report-mobile-row { display: grid; grid-template-columns: minmax(110px, .42fr) minmax(0, 1fr); gap: .75rem; padding: .45rem 0; }
        .report-mobile-row + .report-mobile-row { border-top: 1px solid #eef7ec; }
        .report-mobile-label { color: #5a7a64; font-size: .74rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .report-mobile-value { color: #0d1f18; font-weight: 700; overflow-wrap: anywhere; }
        .history-card-list { display: none; gap: .75rem; }
        @media (max-width: 991.98px) { .report-summary-grid { grid-template-columns: 1fr; } }
        @media (max-width: 767.98px) {
            .report-action-bar, .report-action-bar .btn { width: 100%; }
            .report-results-table, .report-history-table { display: none; }
            .report-mobile-list, .history-card-list { display: grid; }
            .report-mobile-row { grid-template-columns: 1fr; gap: .2rem; }
        }

        /* -- dark theme overrides (matches farmer dashboard palette) -- */
        .report-page { color: rgba(255,255,255,.85); }
        .report-stat, .report-mobile-card { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); }
        .report-stat-label, .report-mobile-label { color: rgba(255,255,255,.5); }
        .report-stat-value, .report-mobile-value { color: #fff; }
        .report-page .text-muted { color: rgba(255,255,255,.5) !important; }
        .report-page .card { background: var(--ic-green-950, #0D1F18); border-color: rgba(255,255,255,.12); color: rgba(255,255,255,.85); }
        .report-page .card-header { background: rgba(255,255,255,.03); border-color: rgba(255,255,255,.08) !important; }
        .report-page .table { color: rgba(255,255,255,.82); }
        .report-page .table thead th { background: rgba(255,255,255,.04) !important; color: rgba(255,255,255,.5) !important; border-color: rgba(255,255,255,.08); }
        .report-page .table td, .report-page .table th { color: rgba(255,255,255,.82) !important; border-color: rgba(255,255,255,.08); }
        .report-page .table-hover tbody tr:hover { background: rgba(255,255,255,.05) !important; }
        .report-page .form-control, .report-page .form-select {
            background-color: rgba(255,255,255,.05);
            border-color: rgba(255,255,255,.18);
            color: #fff;
        }
        .report-page .form-control::placeholder { color: rgba(255,255,255,.35); }
        .report-page .form-select option { color: var(--ic-ink, #0d1f18); }
        .report-page .form-label { color: rgba(255,255,255,.55); }
        .report-page .badge.text-bg-light { background: rgba(255,255,255,.08) !important; color: rgba(255,255,255,.75) !important; }
        .report-page .btn-outline-secondary { color: #fff; border-color: rgba(255,255,255,.28); }
        .report-page .btn-outline-secondary:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.6); color: #fff; }
    </style>

    <div class="report-page">
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">Analytics Workspace</div>
            <h1 class="h2 fw-bold mb-2">Reports</h1>
            <p class="mb-0 text-white-50">Generate filtered reports, print layouts, export CSV, and review report history.</p>
        </div>
    </section>

    <div class="report-shell">
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
                        <button class="btn btn-outline-primary" formaction="{{ route('reports.print') }}" formmethod="GET" formtarget="_blank" type="submit">Print Report</button>
                        <button class="btn btn-outline-secondary" formaction="{{ route('reports.export') }}" formmethod="GET" type="submit">Export CSV</button>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($selected)
        <div class="report-summary-grid">
            <div class="report-stat"><div class="report-stat-label">Report Type</div><div class="h5 fw-bold mb-0 mt-2">{{ $selected }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Rows Found</div><div class="report-stat-value">{{ number_format($rows->count()) }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Generated</div><div class="h5 fw-bold mb-0 mt-2">{{ now()->format('M d, Y h:i A') }}</div></div>
        </div>

        <div class="card no-lift">
            <div class="card-header border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                    <div><h2 class="h5 mb-1">{{ $selected }} Results</h2><div class="small text-muted">Review the generated rows below, then print or export using the buttons above.</div></div>
                    <span class="badge text-bg-light border align-self-start">{{ number_format($rows->count()) }} row(s)</span>
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
                <div class="card-body"><div class="empty-state text-center p-5"><div class="h5">No report records found</div><div class="text-muted">Try changing filters.</div></div></div>
            @endif
        </div>
    @endif

    <div class="card no-lift">
        <div class="card-header border-0 py-3"><h2 class="h5 mb-1">Report History</h2><div class="small text-muted">Saved generated reports and CSV exports.</div></div>
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
