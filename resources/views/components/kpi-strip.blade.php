@props([
    'kpis' => [],
    'title' => 'Key Indicators',
    'note' => null,
])

{{--
    Role KPI strip. Each KPI comes from App\Services\Analytics\DashboardKpiService:
    key, label, value, unit, note, trend {delta, label, good}, status, status_label, href.
--}}
@once
    <style>
        .kpi-strip { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: .85rem; margin-bottom: 1.5rem; }
        .kpi-tile { position: relative; display: flex; flex-direction: column; gap: .3rem; min-width: 0; padding: 1rem 1.05rem .95rem; border: 1px solid var(--color-border, #D8E0DA); border-radius: 14px; background: var(--color-surface, #fff); color: var(--color-text, #1E2B25); text-decoration: none; transition: border-color .15s ease, transform .15s ease; }
        a.kpi-tile:hover, a.kpi-tile:focus-visible { border-color: var(--color-primary, #123F32); color: var(--color-text, #1E2B25); transform: translateY(-1px); }
        .kpi-tile::before { content: ""; position: absolute; left: 0; top: 14px; bottom: 14px; width: 3px; border-radius: 0 3px 3px 0; background: var(--color-border, #D8E0DA); }
        .kpi-tile.is-good::before { background: var(--color-success, #2d7a4f); }
        .kpi-tile.is-warning::before { background: var(--color-warning, #C58B2A); }
        .kpi-tile.is-critical::before { background: var(--color-danger, #B84A4A); }
        .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        .kpi-label { font-size: .74rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: var(--color-text-muted, #4B5A50); line-height: 1.3; }
        .kpi-status { flex-shrink: 0; font-size: .68rem; font-weight: 700; padding: .12rem .5rem; border-radius: 999px; background: var(--color-surface-muted, #f5f7f4); color: var(--color-text-muted, #4B5A50); white-space: nowrap; }
        .kpi-tile.is-good .kpi-status { background: color-mix(in srgb, var(--color-success, #2d7a4f) 14%, transparent); color: var(--color-success, #2d7a4f); }
        .kpi-tile.is-warning .kpi-status { background: color-mix(in srgb, var(--color-warning, #C58B2A) 16%, transparent); color: var(--color-warning, #C58B2A); }
        .kpi-tile.is-critical .kpi-status { background: color-mix(in srgb, var(--color-danger, #B84A4A) 14%, transparent); color: var(--color-danger, #B84A4A); }
        .kpi-value { font-size: 1.65rem; font-weight: 800; line-height: 1.1; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
        .kpi-unit { font-size: .8rem; font-weight: 600; color: var(--color-text-muted, #4B5A50); margin-left: .15rem; }
        .kpi-trend { display: inline-flex; align-items: center; gap: .25rem; font-size: .76rem; font-weight: 700; font-variant-numeric: tabular-nums; color: var(--color-text-muted, #4B5A50); }
        .kpi-trend.is-good { color: var(--color-success, #2d7a4f); }
        .kpi-trend.is-bad { color: var(--color-danger, #B84A4A); }
        .kpi-trend span { font-weight: 500; }
        .kpi-note { font-size: .78rem; line-height: 1.4; color: var(--color-text-muted, #4B5A50); margin-top: auto; }
        @media (max-width: 575.98px) { .kpi-strip { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .6rem; } .kpi-value { font-size: 1.35rem; } .kpi-tile { padding: .85rem .8rem; } }
    </style>
@endonce

@if (! empty($kpis))
    <div class="dashboard-section-label">
        <div>
            <h2 class="section-title">{{ $title }}</h2>
            @if ($note)
                <p class="section-note">{{ $note }}</p>
            @endif
        </div>
    </div>

    <section class="kpi-strip" aria-label="{{ $title }}">
        @foreach ($kpis as $kpi)
            @php
                $trend = $kpi['trend'] ?? null;
                $trendClass = '';
                if ($trend && ($trend['good'] ?? null) && $trend['delta'] != 0) {
                    $isUp = $trend['delta'] > 0;
                    $trendClass = ($isUp === ($trend['good'] === 'up')) ? 'is-good' : 'is-bad';
                }
                $tag = ! empty($kpi['href']) ? 'a' : 'div';
            @endphp
            <{{ $tag }} @if($tag === 'a') href="{{ $kpi['href'] }}" @endif
                class="kpi-tile {{ ! empty($kpi['status']) ? 'is-'.$kpi['status'] : '' }}"
                data-kpi="{{ $kpi['key'] }}">
                <div class="kpi-head">
                    <div class="kpi-label">{{ $kpi['label'] }}</div>
                    @if (! empty($kpi['status_label']))
                        <span class="kpi-status">{{ $kpi['status_label'] }}</span>
                    @endif
                </div>
                <div class="kpi-value">{{ $kpi['value'] }}@if(! empty($kpi['unit']))<span class="kpi-unit">{{ $kpi['unit'] }}</span>@endif</div>
                @if ($trend)
                    <div class="kpi-trend {{ $trendClass }}">
                        {{ $trend['delta'] > 0 ? '▲' : ($trend['delta'] < 0 ? '▼' : '—') }}
                        {{ number_format(abs($trend['delta']), 1) }}%
                        <span>{{ $trend['label'] }}</span>
                    </div>
                @endif
                @if (! empty($kpi['note']))
                    <div class="kpi-note">{{ $kpi['note'] }}</div>
                @endif
            </{{ $tag }}>
        @endforeach
    </section>
@endif
