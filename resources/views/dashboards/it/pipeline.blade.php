@php
    $age = fn ($m) => $m < 60 ? $m.' min' : ($m < 2880 ? round($m / 60, 1).' h' : round($m / 1440).' days');
    $target = fn ($h) => '< '.($h < 48 ? $h.' h' : round($h / 24).' days');
    $perDay = $it['extra']['per_day'];
    $dur = fn ($s) => $s < 60 ? $s.' s' : floor($s / 60).' min '.($s % 60).' s';
    $pipeLevel = collect($it['pipeline'])->contains('level', 'crit') ? 'crit' : (collect($it['pipeline'])->contains('level', 'warn') ? 'warn' : 'ok');
@endphp
<section class="mfd-card itd-card is-{{ $pipeLevel }}" aria-labelledby="itdPipeTitle" data-it-pipeline>
    <div class="mfd-card-head"><div><h2 id="itdPipeTitle">Data Pipeline Status</h2><p class="itd-sub">Freshness against target, plus records added per day</p>@include('dashboards.it._updated', ['at' => $it['stamps']['pipeline'] ?? $it['stamps']['now']])</div></div>
    <div class="itd-table-wrap">
        <table class="itd-table is-wide">
            <thead><tr><th scope="col">Source</th><th scope="col">Last successful run</th><th scope="col">Status</th><th scope="col" class="itd-num">Processed</th><th scope="col" class="itd-num">Rejected</th><th scope="col">Target</th><th scope="col">Added per day <span class="itd-period">last 7 days</span></th></tr></thead>
            <tbody>
                @foreach ($it['pipeline'] as $p)
                    <tr data-pipeline="{{ $p['key'] }}">
                        <td><strong>{{ $p['name'] }}</strong></td>
                        <td class="itd-mono">
                            @if ($p['at']){{ $p['at']->format('M j, g:i A') }}<small class="d-block">{{ $age($p['age_minutes']) }} ago</small>@else Not available @endif
                        </td>
                        <td>@include('dashboards.it._badge', ['level' => $p['level']])</td>
                        <td class="itd-num">{{ $p['processed'] !== null ? number_format($p['processed']) : 'Not available' }}</td>
                        <td class="itd-num">{{ $p['rejected'] !== null ? number_format($p['rejected']) : 'Not tracked' }}</td>
                        <td class="itd-nowrap">{{ $target($p['target_hours']) }}</td>
                        <td class="itd-spark-cell">
                            @include('dashboards.it._spark', ['counts' => $perDay[$p['key']]['counts'] ?? [], 'label' => $p['name'].' records added per day'])
                            @if (($perDay[$p['key']]['total'] ?? 0) > 0)<small class="d-block">{{ number_format($perDay[$p['key']]['total']) }} added</small>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h3 class="itd-h3" data-it-durations>Last run duration <span class="itd-period">most recent completed run</span></h3>
    @if (count($it['extra']['durations']) === 0)
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No completed processing or prediction run has been recorded yet.'])
    @else
        <dl class="mfd-facts itd-rows">
            @foreach ($it['extra']['durations'] as $d)
                <div><dt>{{ $d['label'] }}</dt><dd>{{ $dur($d['seconds']) }} <small>{{ $d['at']->format('M j, g:i A') }}</small></dd></div>
            @endforeach
        </dl>
    @endif
    @php $w = $it['extra']['weather_fetch']; @endphp
    <h3 class="itd-h3" data-it-weather-api>Weather API reliability <span class="itd-period">last 7 days</span></h3>
    @if ($w['total'] === 0)
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No weather fetch attempts have been logged yet. Every fetch from now on is recorded here.'])
    @else
        <dl class="mfd-facts itd-rows">
            <div><dt>Average response time</dt><dd>{{ number_format($w['avg_ms']) }} <span class="itd-unit">ms</span></dd></div>
            <div><dt>Success rate</dt><dd>@include('dashboards.it._badge', ['level' => $w['rate'] >= 90 ? 'ok' : ($w['rate'] >= 70 ? 'warn' : 'crit'), 'text' => rtrim(rtrim(number_format($w['rate'], 1), '0'), '.').'%']) <span class="itd-unit">{{ $w['success'] }} of {{ $w['total'] }} fetches</span></dd></div>
            <div><dt>Current failure streak</dt><dd>@if ($w['streak'] > 0)@include('dashboards.it._badge', ['level' => $w['streak'] >= 3 ? 'crit' : 'warn', 'text' => $w['streak'].' in a row'])@else 0 @endif</dd></div>
            @if ($w['streak'] > 0 && $w['last_error'])<div><dt>Last error</dt><dd class="itd-mono">{{ \Illuminate\Support\Str::limit($w['last_error'], 80) }}</dd></div>@endif
        </dl>
    @endif
</section>
