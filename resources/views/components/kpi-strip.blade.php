@props([
    'kpis' => [],
    'title' => 'Key Indicators',
    'note' => null,
    'primaryKeys' => null,
    'secondaryTitle' => 'Supporting Metrics',
])

{{--
    Role KPI strip. Each KPI comes from App\Services\Analytics\DashboardKpiService:
    key, label, value, unit, note, trend {delta, label, good}, status, status_label, href.
--}}
@once
    <style>
        .kpi-section { margin-bottom: 1.75rem; }
        .kpi-grid-primary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
        }
        .kpi-grid-secondary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: .75rem;
            margin-top: .85rem;
        }
        .kpi-tile {
            position: relative;
            display: flex;
            flex-direction: column;
            gap: .35rem;
            min-width: 0;
            padding: 1.15rem 1.25rem 1.05rem;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            background: #ffffff;
            color: #1E293B;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(15,23,42,.03);
            transition: border-color .18s ease, transform .18s ease, box-shadow .18s ease;
        }
        a.kpi-tile:hover, a.kpi-tile:focus-visible {
            border-color: #1F4D3A;
            color: #1E293B;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(31,77,58,.08);
        }
        .kpi-tile::before {
            content: "";
            position: absolute;
            left: 0;
            top: 12px;
            bottom: 12px;
            width: 3.5px;
            border-radius: 0 3px 3px 0;
            background: #CBD5E1;
        }
        .kpi-tile.is-good::before { background: #1F4D3A; }
        .kpi-tile.is-warning::before { background: #C58B2A; }
        .kpi-tile.is-critical::before { background: #B84A4A; }
        .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: .5rem; }
        .kpi-label {
            font-size: .75rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: #475569;
            line-height: 1.3;
        }
        .kpi-status {
            flex-shrink: 0;
            font-size: .68rem;
            font-weight: 700;
            padding: .15rem .55rem;
            border-radius: 999px;
            background: #F1F5F9;
            color: #475569;
            white-space: nowrap;
        }
        .kpi-tile.is-good .kpi-status { background: rgba(31,77,58,.12); color: #1F4D3A; }
        .kpi-tile.is-warning .kpi-status { background: rgba(197,139,42,.14); color: #82561c; }
        .kpi-tile.is-critical .kpi-status { background: rgba(184,74,74,.14); color: #B84A4A; }
        .kpi-value {
            font-size: clamp(1.65rem, 2.2vw, 2.1rem);
            font-weight: 800;
            line-height: 1.15;
            color: #0F172A;
            font-variant-numeric: tabular-nums;
            overflow-wrap: anywhere;
        }
        .kpi-unit { font-size: .82rem; font-weight: 600; color: #64748B; margin-left: .2rem; }
        .kpi-trend { display: inline-flex; align-items: center; gap: .25rem; font-size: .76rem; font-weight: 700; font-variant-numeric: tabular-nums; color: #64748B; }
        .kpi-trend.is-good { color: #1F4D3A; }
        .kpi-trend.is-bad { color: #B84A4A; }
        .kpi-trend span { font-weight: 500; }
        .kpi-note { font-size: .8rem; line-height: 1.45; color: #64748B; margin-top: auto; }

        /* Secondary compact tiles */
        .kpi-tile-secondary {
            padding: .8rem .95rem .75rem;
            background: #F8FAFC;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
        }
        .kpi-tile-secondary .kpi-label { font-size: .7rem; }
        .kpi-tile-secondary .kpi-value { font-size: 1.25rem; margin-top: .1rem; }
        .kpi-tile-secondary .kpi-note { font-size: .75rem; }

        @media (max-width: 991.98px) {
            .kpi-grid-primary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
        @media (max-width: 575.98px) {
            .kpi-grid-primary { grid-template-columns: 1fr; gap: .75rem; }
            .kpi-grid-secondary { grid-template-columns: 1fr; gap: .6rem; }
            .kpi-value { font-size: 1.5rem; }
            .kpi-tile { padding: .95rem 1rem; }
        }
    </style>
@endonce

@php
    $primaryList = [];
    $secondaryList = [];

    if (! empty($primaryKeys) && is_array($primaryKeys)) {
        $kpiMap = collect($kpis)->keyBy('key');
        foreach ($primaryKeys as $key) {
            if ($kpiMap->has($key)) {
                $primaryList[] = $kpiMap->get($key);
            }
        }
        foreach ($kpis as $kpi) {
            if (! in_array($kpi['key'] ?? '', $primaryKeys, true)) {
                $secondaryList[] = $kpi;
            }
        }
    } else {
        $primaryList = $kpis;
    }
@endphp

@if (! empty($primaryList) || ! empty($secondaryList))
    <div class="kpi-section">
        <div class="dashboard-section-label mb-2">
            <div>
                <h2 class="section-title">{{ $title }}</h2>
                @if ($note)
                    <p class="section-note mb-0">{{ $note }}</p>
                @endif
            </div>
        </div>

        @if (! empty($primaryList))
            <section class="kpi-grid-primary" aria-label="{{ $title }}">
                @foreach ($primaryList as $kpi)
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
                        <div class="kpi-value {{ preg_match('/^(No (verified )?data available|Insufficient verified data)/', (string) $kpi['value']) ? 'is-empty' : '' }}">{{ $kpi['value'] }}@if(! empty($kpi['unit']))<span class="kpi-unit">{{ $kpi['unit'] }}</span>@endif</div>
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

        @if (! empty($secondaryList))
            <div class="kpi-grid-secondary" aria-label="{{ $secondaryTitle }}">
                @foreach ($secondaryList as $kpi)
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
                        class="kpi-tile kpi-tile-secondary {{ ! empty($kpi['status']) ? 'is-'.$kpi['status'] : '' }}"
                        data-kpi="{{ $kpi['key'] }}">
                        <div class="kpi-head">
                            <div class="kpi-label">{{ $kpi['label'] }}</div>
                            @if (! empty($kpi['status_label']))
                                <span class="kpi-status">{{ $kpi['status_label'] }}</span>
                            @endif
                        </div>
                        <div class="kpi-value">{{ $kpi['value'] }}@if(! empty($kpi['unit']))<span class="kpi-unit">{{ $kpi['unit'] }}</span>@endif</div>
                        @if (! empty($kpi['note']))
                            <div class="kpi-note">{{ $kpi['note'] }}</div>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif
    </div>
@endif
