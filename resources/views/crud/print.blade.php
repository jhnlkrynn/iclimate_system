<!DOCTYPE html>
<html lang="en">
    {{-- The print page of a staff list (CrudController::printView, 2026-10): the office header, the print date, the
         active filters and every row that matches them. On paper the buttons are hidden; there is no sidebar here. --}}
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $title }} – Municipal Agricultural Office, Lian, Batangas</title>
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; padding: 24px; font-family: Arial, Helvetica, sans-serif; font-size: 12pt; color: #000; background: #fff; }
            .print-actions { display: flex; gap: 8px; justify-content: flex-end; margin-bottom: 16px; }
            .print-actions button, .print-actions a { padding: 10px 18px; border: 1px solid {{ config('palette.sage.700') }}; border-radius: 6px; background: #fff; color: {{ config('palette.sage.700') }}; font: inherit; font-weight: 700; text-decoration: none; cursor: pointer; }
            .print-actions button { background: {{ config('palette.sage.700') }}; color: #fff; }
            .print-head { padding-bottom: 10px; margin-bottom: 12px; border-bottom: 2px solid #000; }
            .print-head .office { font-size: 16pt; font-weight: 700; }
            .print-head h1 { margin: 4px 0 0; font-size: 14pt; }
            .print-meta { margin: 0 0 12px; padding: 0; list-style: none; font-size: 11pt; }
            .print-meta li { margin: 2px 0; }
            .print-note { margin: 0 0 12px; padding: 8px 10px; border: 1px solid #000; font-size: 11pt; }
            table { width: 100%; border-collapse: collapse; font-size: 10.5pt; }
            th, td { padding: 5px 7px; border: 1px solid #555; text-align: left; vertical-align: top; }
            thead th { background: {{ config('palette.sage.100') }}; }
            thead { display: table-header-group; }                 /* the column names repeat on every printed page */
            tr { page-break-inside: avoid; }
            @media print {
                body { padding: 0; }
                .no-print { display: none !important; }
                thead th { background: {{ config('palette.sage.100') }} !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                @page { margin: 14mm; }
            }
        </style>
    </head>
    <body>
        <div class="print-actions no-print">
            <a href="{{ $backUrl }}">Back to the list</a>
            <button type="button" onclick="window.print()">Print</button>
        </div>

        <header class="print-head">
            <div class="office">Municipal Agricultural Office – Lian, Batangas</div>
            <h1>{{ $title }}</h1>
        </header>

        <ul class="print-meta" data-print-meta>
            <li><strong>Printed:</strong> {{ $printedAt->format('F j, Y g:i A') }}</li>
            <li data-print-filters><strong>Filters:</strong>
                @if ($filters)
                    {{ collect($filters)->map(fn ($value, $label) => $label.': '.$value)->implode(' · ') }}
                @else
                    None (all records)
                @endif
            </li>
            <li data-print-count><strong>Records:</strong> {{ number_format(min($total, $limit)) }}@if ($total > $limit) of {{ number_format($total) }}@endif</li>
        </ul>

        @if ($total > $limit)
            <p class="print-note">Only the first {{ number_format($limit) }} records are listed. Narrow the search or filters to print the rest.</p>
        @endif

        @if ($total === 0)
            <p class="print-note" data-print-empty>No records match these filters.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        @foreach ($columns as $label)
                            <th>{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            @foreach ($columns as $field => $label)
                                @php $value = data_get($row, $field); @endphp
                                @if (($routeName ?? null) === 'climate-records' && $field === 'source') @php $value = \App\Services\Climate\ClimateRecordHistory::displaySource($value); @endphp @endif
                                <td>{{ $field === 'role' && filled($value) ? \App\Models\User::labelForRole((string) $value) : (filled($value) ? $value : '—') }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <script>
            // Opens the print dialog once the page is ready; "Back to the list" and "Print" stay on screen for later.
            window.addEventListener('load', () => { if (!new URLSearchParams(location.search).has('noprompt')) window.print(); });
        </script>
    </body>
</html>
