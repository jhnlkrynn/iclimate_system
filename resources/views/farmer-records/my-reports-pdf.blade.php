<!doctype html>
{{-- Manuscript – Naicel (2026-10-08), Phase 7: My Reports as a PDF (dompdf). Inline styles only. --}}
<html lang="{{ app()->getLocale() === 'fil' ? 'fil' : 'en' }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('my_reports.title') }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .office { font-size: 11px; font-weight: bold; margin: 0; }
        h1 { font-size: 16px; color: #14532d; margin: 2px 0; }
        h2 { font-size: 13px; color: #14532d; margin: 14px 0 4px; }
        h3 { font-size: 11px; margin: 10px 0 3px; }
        .meta { color: #4b5563; margin: 0 0 8px; }
        .mr-table { width: 100%; border-collapse: collapse; }
        .mr-table th { background: #14532d; color: #fff; text-align: left; padding: 3px 4px; font-size: 9px; }
        .mr-table td { border-bottom: 1px solid #d1d5db; padding: 3px 4px; font-size: 9px; vertical-align: top; }
    </style>
</head>
<body>
    <p class="office">{{ $office }}</p>
    <h1>{{ __('my_reports.title') }}</h1>
    <p class="meta">{{ __('my_reports.for', ['name' => $user->name]) }}@if ($barangay) · {{ $barangay }}@endif · {{ __('my_reports.printed', ['date' => $printedAt->format('M j, Y g:i A')]) }}</p>
    @include('farmer-records._my-reports-body')
</body>
</html>
