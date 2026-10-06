<x-app-layout>
    <style>
        .report-shell { display: grid; gap: 1rem; }
        .report-summary-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 1rem; }
        .report-stat { border: 1px solid #d4edda; border-radius: 18px; background: #fff; padding: 1rem; }
        .report-stat-label { color: #476651; font-size: .95rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; }
        .report-stat-value { color: #0d1f18; font-size: 2rem; font-weight: 900; line-height: 1; margin-top: .35rem; }
        .report-action-bar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
        .report-live-strip { display: grid; grid-template-columns: 1.4fr repeat(5, minmax(0, 1fr)); gap: .75rem; }
        .report-live-item { border: 1px solid var(--ic-border); border-radius: 14px; background: var(--ic-panel); padding: .85rem; min-width: 0; }
        .report-live-item strong { display: block; color: var(--ic-ink); font-size: 1.4rem; line-height: 1.15; }
        .report-live-item span { display: block; color: var(--ic-muted); font-size: .9rem; font-weight: 900; text-transform: uppercase; letter-spacing: .04em; margin-top: .3rem; }
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
        .report-page { font-size: 1.05rem; }
        .report-page .form-label,
        .report-page .form-control,
        .report-page .form-select,
        .report-page .btn { font-size: 1rem; }
        .report-page .form-control,
        .report-page .form-select,
        .report-page .btn { min-height: 48px; }
        .report-page .small { font-size: .95rem; }
        .report-table { font-size: 1rem; }
        .report-table th { white-space: nowrap; font-size: 1rem; }
        .report-table th, .report-table td { padding: .9rem .85rem; vertical-align: middle; }
        .report-mobile-list { display: none; gap: .75rem; }
        .report-mobile-card { border: 1px solid #d4edda; border-radius: 12px; background: #fff; padding: 1rem; }
        .report-mobile-row { display: grid; grid-template-columns: minmax(110px, .42fr) minmax(0, 1fr); gap: .75rem; padding: .45rem 0; }
        .report-mobile-row + .report-mobile-row { border-top: 1px solid #eef7ec; }
        .report-mobile-label { color: #476651; font-size: .9rem; font-weight: 900; text-transform: uppercase; letter-spacing: .03em; }
        .report-mobile-value { color: #0d1f18; font-size: 1.05rem; font-weight: 700; overflow-wrap: anywhere; }
        @media (max-width: 991.98px) {
            .report-summary-grid { grid-template-columns: 1fr; }
            .report-live-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .report-live-item:first-child { grid-column: 1 / -1; }
        }
        @media (max-width: 767.98px) {
            .report-action-bar, .report-action-bar .btn { width: 100%; }
            .report-results-table { display: none; }
            .report-mobile-list { display: grid; }
            .report-mobile-row { grid-template-columns: 1fr; gap: .2rem; }
            .report-live-strip { grid-template-columns: 1fr; }
            .report-shell { gap: .75rem; }
            .report-stat { padding: .75rem; border-radius: 14px; }
            .report-stat-value { font-size: 1.75rem; }
            .report-live-item { padding: .65rem; }
            .card-body.p-4 { padding: 1rem !important; }
            .card-body.p-3 { padding: .75rem !important; }
            .report-mobile-card { padding: .75rem; }
            .card-header.py-3 { padding-top: .85rem !important; padding-bottom: .85rem !important; }
        }
    </style>

    <div class="report-page">
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">iClimate Reports</div>
            <h1 class="h2 fw-bold mb-2">Reports</h1>
            <p class="mb-0" style="color: var(--ic-ink-mid);">Choose a report and dates, then press Generate Report.</p>
        </div>
    </section>

    @if ($analyticsRun && in_array($analyticsRun->status, ['pending', 'processing'], true))
        <div class="alert alert-info border" data-analytics-run="{{ route('analytics-runs.show', $analyticsRun) }}">
            <strong>Preparing your report&hellip;</strong>
            This page will refresh by itself when the report is ready.
        </div>
    @elseif ($analyticsRun?->status === 'failed')
        <div class="alert alert-danger border">
            <strong>Report could not be prepared.</strong> Please try again later.
        </div>
    @endif

    <div class="report-shell">
    <div class="card no-lift">
        <div class="card-body p-3">
            <div class="report-live-strip">
                <div class="report-live-item">
                    <strong>Latest records</strong>
                    <span>Refreshed {{ $sourceStats['last_refreshed']->shortDateTime('M d, Y') }}</span>
                </div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['climate_records']) }}</strong><span>Climate</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['rice_production']) }}</strong><span>Rice harvests</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['farmers']) }}</strong><span>Farmers</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['advisories']) }}</strong><span>Advisories</span></div>
                <div class="report-live-item"><strong>{{ number_format($sourceStats['community_posts']) }}</strong><span>Posts</span></div>
            </div>
        </div>
    </div>

    @include('reports._lower-yield')

    <details class="ic-collapse" data-force-open="true">
        <summary><span class="ic-collapse-title">Generate Report</span></summary>
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
                        @if ($selectedReport)
                            <a class="btn report-white-action" href="{{ route('reports.print', ['report' => $selectedReport->id]) }}" target="_blank">Print Report</a>
                            <a class="btn report-white-action" href="{{ route('reports.export', ['report' => $selectedReport->id]) }}">Download as Spreadsheet</a>
                        @endif
                    @endif
                </div>
            </form>
        </div>
    </div>
    </details>

    @if ($selected)
        @if ($reportTypeInfo)
            <div class="card no-lift">
                <div class="card-body p-3">
                    <div class="fw-bold mb-1">About this report</div>
                    <p class="mb-1">{{ $reportTypeInfo['purpose'] }}</p>
                    <p class="mb-0 text-muted small"><strong>Good for:</strong> {{ $reportTypeInfo['good_for'] }}</p>
                </div>
            </div>
        @endif

        <div class="report-summary-grid">
            <div class="report-stat"><div class="report-stat-label">Report Type</div><div class="h5 fw-bold mb-0 mt-2">{{ $selected }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Records Found</div><div class="report-stat-value">{{ number_format($rows->total()) }}</div></div>
            <div class="report-stat"><div class="report-stat-label">Generated</div><div class="h5 fw-bold mb-0 mt-2">{{ $generatedAt?->format('M d, Y, g:i A') }}</div></div>
        </div>

        @if (! empty($reportSummary))
            <div class="card no-lift">
                <div class="card-body p-3">
                    <div class="fw-bold mb-2">Result Summary</div>
                    {{-- A climate report has two groups (historical station months, entered records) that are never averaged together. --}}
                    @foreach (collect($reportSummary)->groupBy(fn ($item) => $item['group'] ?? '') as $group => $items)
                        @if ($group !== '')
                            <div class="fw-semibold {{ $loop->first ? '' : 'mt-3' }}">{{ $group }}</div>
                            <div class="small text-muted mb-2">{{ $items->first()['source'] ?? '' }}</div>
                        @endif
                        @if ($items->first()['note'] ?? null)
                            <p class="mb-0 text-muted">{{ $items->first()['note'] }}</p>
                        @else
                            <div class="report-summary-grid">
                                @foreach ($items as $item)
                                    <div class="report-stat"><div class="report-stat-label">{{ $item['label'] }}</div><div class="h5 fw-bold mb-0 mt-2">{{ $item['value'] }}</div></div>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        @if ($weatherBasis)
            <div class="card no-lift">
                <div class="card-body p-3">
                    <div class="fw-bold mb-1">Weather During This Period</div>
                    @php
                        $h = $weatherBasis['historical'];
                        $e = $weatherBasis['entered'];
                        $amount = fn (?float $value, string $unit) => $value === null ? 'not available' : number_format($value, 1).$unit;
                    @endphp
                    {{-- Two separate parts: a month's rainfall total and one day's reading are never averaged together. --}}
                    @if ($h)
                        <p class="mb-1"><strong>Historical weather</strong> ({{ $weatherBasis['historical_source'] }}, iClimate's weather reference for Lian): {{ $h['months'] }} month(s), {{ $h['first'] === $h['last'] ? $h['first'] : $h['first'].' – '.$h['last'] }}, with {{ $amount($h['total_rainfall'], ' mm') }} total rainfall and an average of {{ $amount($h['avg_temperature'], '°C') }}.</p>
                    @else
                        <p class="mb-1 text-muted"><strong>Historical weather:</strong> no station months for {{ $weatherBasis['range_label'] }}.</p>
                    @endif
                    @if ($e)
                        <p class="mb-0"><strong>Entered climate records:</strong> {{ number_format($e['records']) }} record(s) during {{ $weatherBasis['range_label'] }}, averaging {{ $amount($e['avg_rainfall'], ' mm rainfall') }} per record and {{ $amount($e['avg_temperature'], '°C') }}.</p>
                    @else
                        <p class="mb-0 text-muted"><strong>Entered climate records:</strong> no entered climate records for this period.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="card no-lift">
            <div class="card-header border-0 py-3">
                <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
                    <div><h2 class="h5 mb-1">{{ $selected }} Results</h2><div class="small text-muted">Review the results below. You can print or download them.</div></div>
                    <div class="report-result-actions d-flex flex-wrap gap-2 align-items-start">
                        <span class="badge text-bg-light border align-self-start">{{ number_format($rows->total()) }} record(s){{ $rows->lastPage() > 1 ? ' - page '.$rows->currentPage().' of '.$rows->lastPage() : '' }}</span>
                        @if ($selectedReport)
                            <a class="btn btn-sm fw-bold report-white-action" href="{{ route('reports.print', ['report' => $selectedReport->id]) }}" target="_blank">Print Report</a>
                            <a class="btn btn-sm fw-bold report-white-action" href="{{ route('reports.export', ['report' => $selectedReport->id]) }}">Download as Spreadsheet</a>
                        @endif
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
                <div class="card-body border-top d-flex justify-content-end">{{ $rows->links() }}</div>
            @else
                <div class="card-body">
                    <div class="report-empty-state text-center p-5">
                        <div class="h5 mb-2">No records found</div>
                        <div class="text-muted">No current iClimate records match the selected filters. Try widening the date range or choosing All Barangays.</div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    </div>
    @if ($analyticsRun && in_array($analyticsRun->status, ['pending', 'processing'], true))
        <script>
            (() => {
                const banner = document.querySelector('[data-analytics-run]');
                if (!banner) return;
                const check = async () => {
                    try {
                        const response = await fetch(banner.dataset.analyticsRun, { headers: { Accept: 'application/json' }, loader: false });
                        const run = await response.json();
                        if (run.status === 'completed' || run.status === 'failed') window.location.reload();
                    } catch (_) {}
                };
                window.setInterval(check, 2500);
            })();
        </script>
    @endif
    </div>
</x-app-layout>
