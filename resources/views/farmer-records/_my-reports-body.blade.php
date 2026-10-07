{{-- Manuscript – Naicel (2026-10-08), Phase 7: the body of My Reports, shared by the page and the PDF. --}}
@php
    $R = fn (string $key, array $replace = []) => __('my_reports.'.$key, $replace);
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
    $tz = \App\Support\BusinessClock::timezone();
    $levels = __('yield_request.impact_levels');
@endphp
<section class="mr-section" data-prediction-summary>
    <h2>{{ $R('prediction_title') }}</h2>

    <h3>{{ $R('estimate_title') }}</h3>
    @if ($estimates->filter(fn ($e) => $e['yield'] !== null)->isEmpty())
        <p>{{ $R('none_estimate') }}</p>
    @else
        <table class="mr-table">
            <thead><tr><th>{{ $R('farm') }}</th><th>Barangay</th><th>{{ $R('estimate') }}</th></tr></thead>
            <tbody>@foreach ($estimates as $e)<tr><td>{{ $e['farm'] }}</td><td>{{ $e['barangay'] ?? '—' }}</td><td>{{ $e['yield'] !== null ? $R('t_ha', ['value' => $num($e['yield'])]) : '—' }}</td></tr>@endforeach</tbody>
        </table>
    @endif

    <h3>{{ $R('requests_title') }}</h3>
    @if ($requests->isEmpty())
        <p>{{ $R('none_requests') }}</p>
    @else
        <table class="mr-table">
            <thead><tr><th>{{ $R('season') }}</th><th>Barangay</th><th>{{ $R('per_ha') }}</th><th>{{ $R('total') }}</th><th>{{ $R('outlook') }}</th></tr></thead>
            <tbody>
                @foreach ($requests as $r)
                    <tr><td>{{ __('yield_request.'.strtolower($r->season)) }}</td><td>{{ $r->barangay }}</td><td>{{ $R('t_ha', ['value' => $num($r->predicted_yield)]) }}</td><td>{{ $r->total_tons !== null ? $R('tons', ['value' => $num($r->total_tons)]) : '—' }}</td><td>{{ $levels[$r->impact_level] ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h3>{{ $R('harvests_title') }}</h3>
    @if ($harvests->isEmpty())
        <p>{{ $R('none_harvests') }}</p>
    @else
        <table class="mr-table">
            <thead><tr><th>{{ $R('harvested') }}</th><th>{{ $R('season') }}</th><th>{{ $R('predicted') }}</th><th>{{ $R('actual') }}</th></tr></thead>
            <tbody>@foreach ($harvests as $h)<tr><td>{{ $h->harvested_on?->format('M j, Y') ?? '—' }}</td><td>{{ $h->season }}</td><td>{{ $R('t_ha', ['value' => $num($h->predicted_yield)]) }}</td><td>{{ $R('t_ha', ['value' => $num($h->actual_yield)]) }}</td></tr>@endforeach</tbody>
        </table>
    @endif
</section>

<section class="mr-section" data-advisory-summary>
    <h2>{{ $R('advisory_title') }}</h2>
    <p>{{ $barangay ? $R('advisory_note', ['barangay' => $barangay]) : $R('advisory_note_lian') }} {{ $R('alerts', ['count' => $alerts['count'], 'read' => $alerts['read']]) }}</p>
    @if ($advisories->isEmpty())
        <p>{{ $R('none_advisories') }}</p>
    @else
        <table class="mr-table">
            <thead><tr><th>{{ $R('published') }}</th><th>Advisory</th><th>{{ $R('severity') }}</th><th>{{ $R('action') }}</th></tr></thead>
            <tbody>
                @foreach ($advisories as $a)
                    <tr><td>{{ $a->published_at?->copy()->timezone($tz)->format('M j, Y') }}</td><td>{{ $a->title }}@if ($a->target_barangay) ({{ $a->target_barangay }})@endif</td><td>{{ ucfirst((string) $a->severity) }}</td><td>{{ $a->recommended_action }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
</section>
