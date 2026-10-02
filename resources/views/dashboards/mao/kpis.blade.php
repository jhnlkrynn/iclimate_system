<div class="mtd-kpis" data-mao-kpis style="--mtd-kpi-count: {{ count($mao['kpis']) }}">
    @foreach ($mao['kpis'] as $k)
        <a class="mtd-kpi" data-kpi="{{ $k['key'] }}" href="{{ $k['href'] }}">
            <div class="mtd-kpi-top">
                <span class="mtd-kpi-icon">@include('dashboards.mao.icon', ['name' => $k['icon']])</span>
                <span class="mtd-kpi-label">{{ $k['label'] }}</span>
                <span class="mtd-info" tabindex="0" role="note" aria-label="{{ $k['tip'] }}" data-tip="{{ $k['tip'] }}">i</span>
            </div>
            <div class="mtd-kpi-value"><span>{{ $k['value'] }}</span>@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif</div>
            <div class="mtd-kpi-context">{{ $k['context'] }}</div>
            @if ($k['trend'])
                <div class="mtd-trend {{ $k['trend']['delta'] > 0 ? 'is-up' : ($k['trend']['delta'] < 0 ? 'is-down' : 'is-flat') }}">
                    <span aria-hidden="true">{{ $k['trend']['delta'] > 0 ? '↑' : ($k['trend']['delta'] < 0 ? '↓' : '→') }}</span>
                    <strong>{{ $k['trend']['delta'] > 0 ? '+' : '' }}{{ rtrim(rtrim(number_format($k['trend']['delta'], 1), '0'), '.') }}%</strong> {{ $k['trend']['label'] }}
                </div>
                <div class="mtd-basis">{{ $k['trend']['basis'] }}</div>
            @endif
        </a>
    @endforeach
</div>
