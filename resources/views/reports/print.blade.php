<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }}</title>
        @vite(['resources/css/app.css'])
        <style>
            body { color: #1b2b23; }
            .print-header { border-bottom: 3px solid #2d6a4f; padding-bottom: 1rem; margin-bottom: 1rem; }
            .print-meta { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: .75rem; margin-bottom: 1rem; }
            .meta-box { border: 1px solid #d4edda; border-radius: 8px; background: #fff; padding: .75rem; }
            .meta-label { color: #5a7a64; font-size: .72rem; font-weight: 800; text-transform: uppercase; }
            .meta-value { font-weight: 800; margin-top: .2rem; }
            table { font-size: .9rem; }
            thead th { background: #f0f7f4 !important; color: #1b2b23; white-space: nowrap; }
            td, th { vertical-align: top; }
            @media print {
                .no-print { display: none !important; }
                body { background: #fff !important; }
                .container { max-width: none; width: 100%; padding: 0; }
                .card { box-shadow: none !important; border: 0 !important; }
                a[href]::after { content: ""; }
                table { page-break-inside: auto; }
                tr { page-break-inside: avoid; page-break-after: auto; }
            }
            @media (max-width: 767.98px) { .print-meta { grid-template-columns: 1fr; } }
        </style>
    </head>
    <body class="bg-light">
        <main class="container py-4">
            <div class="print-header d-flex justify-content-between align-items-start gap-3">
                <div>
                    <div class="text-uppercase fw-bold text-success small">iClimate Report</div>
                    <h1 class="h3 mb-1">{{ $title }}</h1>
                    <p class="text-muted mb-0">Generated {{ now()->format('Y-m-d H:i') }}</p>
                </div>
                <div class="d-flex gap-2 no-print">
                    <button class="btn btn-primary" onclick="window.print()">Print</button>
                    <button class="btn btn-outline-secondary" onclick="window.close()">Close</button>
                </div>
            </div>

            <section class="print-meta">
                <div class="meta-box"><div class="meta-label">Rows</div><div class="meta-value">{{ number_format($rows->count()) }}</div></div>
                <div class="meta-box"><div class="meta-label">Date Range</div><div class="meta-value">{{ ($filters['date_from'] ?? null) ?: 'Start' }} to {{ ($filters['date_to'] ?? null) ?: 'Latest' }}</div></div>
                <div class="meta-box"><div class="meta-label">Barangay</div><div class="meta-value">{{ ($filters['barangay'] ?? null) ?: 'All' }}</div></div>
                <div class="meta-box"><div class="meta-label">Season / Year</div><div class="meta-value">{{ ($filters['season'] ?? null) ?: 'All seasons' }}{{ ! empty($filters['year']) ? ' / '.$filters['year'] : '' }}</div></div>
            </section>

            <div class="alert alert-light border small">
                This report is generated from iClimate records using the selected filters. Review source records before making final operational decisions.
            </div>
            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
                        <tbody>@forelse ($rows as $row)<tr>@foreach ($columns as $field => $label)<td>{{ data_get($row, $field) }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($columns) }}" class="text-center text-muted">No records found.</td></tr>@endforelse</tbody>
                    </table>
                </div>
            </div>
        </main>
    </body>
</html>
