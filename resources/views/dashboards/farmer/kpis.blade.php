@if (count($f['kpis']) > 0)
    <section aria-labelledby="mfdKpiTitle" data-mfd-kpis>
        <h2 class="mfd-section-title" id="mfdKpiTitle">My Farm Today</h2>
        <div class="mfd-kpis" style="--mfd-kpi-count: {{ count($f['kpis']) }}">
            @foreach ($f['kpis'] as $k)
                <article class="mfd-kpi {{ isset($k['level']) ? 'is-'.$k['level'] : '' }}" data-kpi="{{ $k['key'] }}">
                    <div class="mfd-kpi-label">{{ $k['label'] }}</div>
                    <div class="mfd-kpi-value {{ ! empty($k['compact']) ? 'is-compact' : '' }}">
                        <span>{{ $k['value'] }}</span>@if ($k['unit'])<small>{{ $k['unit'] }}</small>@endif
                    </div>
                    <div class="mfd-kpi-context">{{ $k['context'] }}</div>
                    @if ($k['note'])<div class="mfd-kpi-note">{{ $k['note'] }}</div>@endif
                    @if ($k['cta'])<a class="mfd-btn is-quiet" href="{{ $k['href'] }}">{{ $k['cta'] }}</a>
                    @else<a class="mfd-link" href="{{ $k['href'] }}">See details</a>@endif
                </article>
            @endforeach
        </div>
    </section>
@endif
