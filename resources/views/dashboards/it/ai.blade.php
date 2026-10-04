@php $a = $it['ai']; $t = $it['extra']['ai_trend']; @endphp
<section class="mfd-card itd-card" aria-labelledby="itdAiTitle" data-it-ai>
    <div class="mfd-card-head"><div><h2 id="itdAiTitle">Climaite AI Usage</h2><p class="itd-sub">Today's queries and the last 7 days</p>@include('dashboards.it._updated', ['at' => $it['stamps']['ai'] ?? $it['stamps']['now']])</div></div>
    @if (($a['state'] ?? 'error') === 'error')
        @include('dashboards.it._state', ['kind' => 'error', 'text' => 'Unable to retrieve AI usage.'])
    @else
        <dl class="mfd-facts itd-rows">
            <div><dt>Queries today</dt><dd>{{ number_format($a['queries']) }}</dd></div>
            <div><dt>Failed responses</dt><dd>@if ($a['failed'] > 0)@include('dashboards.it._badge', ['level' => 'warn', 'text' => number_format($a['failed'])])@else{{ number_format($a['failed']) }}@endif</dd></div>
            <div><dt>Average response time</dt><dd>{{ $a['avg_ms'] !== null ? number_format($a['avg_ms']) : 'Not available' }}@if ($a['avg_ms'] !== null) <span class="itd-unit">ms</span>@endif</dd></div>
        </dl>
        <h3 class="itd-h3" data-it-ai-trend>Queries per day <span class="itd-period">last 7 days</span></h3>
        @if (($t['state'] ?? 'error') === 'ok')
            <div class="itd-spark-row">@include('dashboards.it._spark', ['counts' => $t['counts'], 'label' => 'Climaite AI queries per day'])<small>{{ number_format($t['total']) }} queries</small></div>
        @else
            @include('dashboards.it._state', ['kind' => ($t['state'] ?? '') === 'empty' ? 'empty' : 'error', 'text' => ($t['state'] ?? '') === 'empty' ? 'No Climaite AI queries in the last 7 days. Usage will show up here as people ask questions.' : 'Unable to read AI usage.'])
        @endif
    @endif
</section>
