{{-- KPI cards. Each card shows more about its number in the shared information pop-up (resources/js/info-modal.js);
     the module page stays one click away inside that pop-up. "Barangays Monitored" opens the same pop-up as the
     Municipal Risk Overview, built from the ratings that colour the map. --}}
<div class="mtd-kpis" data-mao-kpis style="--mtd-kpi-count: {{ count($mao['kpis']) }}">
    @foreach ($mao['kpis'] as $k)
        @php $riskCategory = $k['risk_category'] ?? null; @endphp
        <button type="button" class="mtd-kpi" data-kpi="{{ $k['key'] }}" aria-haspopup="dialog"
            @if ($riskCategory) data-mao-risk-open="{{ $riskCategory }}"
            @else data-ic-template="mtdKpiInfo-{{ $k['key'] }}" data-ic-title="{{ $k['label'] }}" data-ic-icon="{{ $k['icon'] }}"
                data-ic-description="{{ $k['context'] }}" data-ic-kicker="MAO Dashboard"
                data-ic-href="{{ $k['href'] }}" data-ic-href-label="Open {{ ['farmers' => 'Farmer Profiles', 'weather' => 'Weather Forecast', 'yield' => 'Rice Production', 'advisories' => 'Planting Advisories'][$k['key']] ?? 'page' }}"
                data-ic-note="Source: {{ ['farmers' => 'iClimate farmer profiles (recorded)', 'weather' => 'online weather service (live reading)', 'yield' => 'iClimate modeling dataset (historical)', 'advisories' => 'iClimate planting advisories (recorded)'][$k['key']] ?? 'iClimate records' }}. Updated {{ $mao['generated_at']->format('M j, Y g:i A') }}."
            @endif>
            <span class="mtd-kpi-top">
                <span class="mtd-kpi-icon">@include('dashboards.mao.icon', ['name' => $k['icon']])</span>
                <span class="mtd-kpi-label">{{ $k['label'] }}</span>
            </span>
            <span class="mtd-kpi-value"><span data-kpi-value>{{ $k['value'] }}</span>@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif</span>
            <span class="mtd-kpi-context">{{ $k['context'] }}</span>
            @if ($k['trend'])
                <span class="mtd-trend {{ $k['trend']['delta'] > 0 ? 'is-up' : ($k['trend']['delta'] < 0 ? 'is-down' : 'is-flat') }}">
                    <span aria-hidden="true">{{ $k['trend']['delta'] > 0 ? '↑' : ($k['trend']['delta'] < 0 ? '↓' : '→') }}</span>
                    <strong>{{ $k['trend']['delta'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($k['trend']['delta'], 1), '0'), '.') }}%</strong> {{ $k['trend']['label'] }}
                </span>
            @endif
            <span class="mtd-kpi-more">@include('dashboards.mao.icon', ['name' => 'info']){{ $riskCategory ? 'View barangays and status' : 'View details' }}</span>
        </button>
        @unless ($riskCategory)
            <template id="mtdKpiInfo-{{ $k['key'] }}">
                <div class="ic-risk-count"><strong>{{ $k['value'] }}</strong>@if ($k['unit'])<span>{{ $k['unit'] }}</span>@endif<span>{{ $k['context'] }}</span></div>
                <section class="fad-m-section"><h3>What this shows</h3><p class="fad-m-body">{{ $k['tip'] }}</p></section>
                @if ($k['trend'])
                    <section class="fad-m-section">
                        <h3>Change</h3>
                        <dl class="fad-m-details">
                            <div><dt>Change</dt><dd>{{ $k['trend']['delta'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($k['trend']['delta'], 1), '0'), '.') }}% {{ $k['trend']['label'] }}</dd></div>
                            <div><dt>Basis</dt><dd>{{ $k['trend']['basis'] }}</dd></div>
                        </dl>
                    </section>
                @endif
            </template>
        @endunless
    @endforeach
</div>
