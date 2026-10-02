@php $a = $it['ai']; $t = $it['extra']['ai_trend']; @endphp
<section class="itd-card" aria-labelledby="itdAiTitle" data-it-ai>
    <div class="itd-card-head"><div><h2 id="itdAiTitle">Climaite AI Usage</h2><p class="itd-sub">Today's queries and the last 7 days</p>@include('dashboards.it._updated', ['at' => $it['stamps']['ai'] ?? $it['stamps']['now']])</div></div>
    @if (($a['state'] ?? 'error') === 'error')
        <p class="itd-error" role="alert">Unable to retrieve AI usage.</p>
    @else
        <dl class="itd-rows">
            <div><dt>Queries today</dt><dd class="itd-num">{{ number_format($a['queries']) }}</dd></div>
            <div><dt>Failed responses</dt><dd class="itd-num">@if ($a['failed'] > 0)<span class="itd-badge is-warn"><span aria-hidden="true">▲</span> {{ number_format($a['failed']) }}</span>@else{{ number_format($a['failed']) }}@endif</dd></div>
            <div><dt>Average response time</dt><dd class="itd-num">{{ $a['avg_ms'] !== null ? number_format($a['avg_ms']) : 'Not available' }}@if ($a['avg_ms'] !== null) <span class="itd-unit">ms</span>@endif</dd></div>
        </dl>
        <h3 class="itd-h3" data-it-ai-trend>Queries per day <span class="itd-period">last 7 days</span></h3>
        @if (($t['state'] ?? 'error') === 'ok')
            <div class="itd-spark-row">@include('dashboards.it._spark', ['counts' => $t['counts'], 'label' => 'Climaite AI queries per day'])<small class="itd-muted">{{ number_format($t['total']) }} <span class="itd-unit">queries</span></small></div>
        @else
            <p class="itd-empty">{{ ($t['state'] ?? '') === 'empty' ? 'No Climaite AI queries in the last 7 days. Usage will show up here as people ask questions.' : 'Unable to read AI usage.' }}</p>
        @endif
    @endif
</section>
