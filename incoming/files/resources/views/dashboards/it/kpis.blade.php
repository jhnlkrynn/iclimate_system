@php $glyph = ['ok' => '✓', 'warn' => '!', 'crit' => '✕']; @endphp
<div class="itd-kpis" data-it-kpis style="--itd-kpi-count: {{ count($it['kpis']) }}">
    @foreach ($it['kpis'] as $k)
        @php $level = $k['level'] ?? null; @endphp
        <a class="itd-kpi {{ $level ? 'is-'.$level : '' }}" data-kpi="{{ $k['key'] }}" href="{{ $k['href'] ?? '#' }}">
            <div class="itd-kpi-top">
                <span class="itd-kpi-label">{{ $k['label'] }}</span>
                @if (! empty($k['tip']))<span class="itd-info" tabindex="0" role="note" aria-label="{{ $k['tip'] }}" data-tip="{{ $k['tip'] }}">i</span>@endif
            </div>
            @if (($k['state'] ?? 'ok') === 'error')
                <div class="itd-error" role="alert">{{ $k['message'] }}</div>
            @else
                <div class="itd-kpi-value {{ ! empty($k['compact']) ? 'is-compact' : '' }}">
                    @if ($level)<span class="itd-glyph" aria-hidden="true">{{ $glyph[$level] }}</span>@endif
                    <span>{{ $k['value'] }}</span>
                </div>
                <div class="itd-kpi-caption">{{ $k['caption'] }}</div>
                <div class="itd-kpi-sub">{{ $k['sub'] }}</div>
            @endif
        </a>
    @endforeach
</div>
