<!doctype html>
{{-- Manuscript – Naicel (2026-10-08), Phase 7: a report as a PDF (dompdf). Plain HTML and inline styles only. --}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 2px; color: #14532d; }
        .office { font-size: 11px; font-weight: bold; margin: 0; }
        .meta { color: #4b5563; margin: 2px 0 10px; }
        .summary { margin: 0 0 10px; padding: 0; list-style: none; }
        .summary li { margin: 1px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #14532d; color: #fff; text-align: left; padding: 4px; font-size: 9px; }
        td { border-bottom: 1px solid #d1d5db; padding: 3px 4px; font-size: 9px; }
        .foot { margin-top: 10px; color: #6b7280; font-size: 8px; }
    </style>
</head>
<body>
    <p class="office">{{ $office }}</p>
    <h1>{{ $title }}</h1>
    <p class="meta">
        {{ $status }} · version {{ $version }} · generated {{ $generatedAt?->format('M j, Y g:i A') }}@if ($author) by {{ $author }}@endif
        @if ($filters !== []) · {{ collect($filters)->map(fn ($v, $k) => str($k)->headline().': '.$v)->implode(', ') }}@endif
    </p>
    @if ($summary !== [])
        <ul class="summary">
            @foreach ($summary as $item)
                @if (isset($item['label']))<li><strong>{{ $item['label'] }}:</strong> {{ $item['value'] ?? '' }}</li>@elseif (isset($item['note']))<li>{{ $item['note'] }}</li>@endif
            @endforeach
        </ul>
    @endif
    @if ($rows === [])
        <p>No records for these filters.</p>
    @else
        <table>
            <thead><tr>@foreach ($columns as $label)<th>{{ $label }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>@foreach ($columns as $field => $label)<td>{{ data_get($row, $field) }}</td>@endforeach</tr>
                @endforeach
            </tbody>
        </table>
        @if ($truncated)<p class="foot">The first {{ count($rows) }} of {{ $total }} rows are shown. Download Excel for all rows.</p>@endif
    @endif
    <p class="foot">iClimate · {{ $office }} · printed {{ now(\App\Support\BusinessClock::timezone())->format('M j, Y g:i A') }}</p>
</body>
</html>
