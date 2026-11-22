{{-- Farmer – Naicel (2026-11-23, audit M-8): what each dashboard card's summary panel shows, one <template> per card,
     named like the card's data-panel ("farm-size", "days-to-harvest", "expected-harvest", "weather", "risk", ...).
     This is one of the dashboard's slots (DashboardController::FARMER_SLOTS), so the timed refresh brings the panels up
     to date together with the cards. The drawer in dashboards/farmer.blade.php copies the clicked card's template in;
     opening a panel asks the server nothing. Only data this page already has ($f) is used. --}}
@php
    $panels = [];
    $panelFor = ['farm' => 'farm-size', 'crop' => 'days-to-harvest', 'expected' => 'expected-harvest'];
    // The figures in the tiles: farm size, days to harvest, expected harvest, rain, risk, advisories, ...
    foreach ($f['kpis'] ?? [] as $tile) {
        if (! empty($tile['detail'])) {
            $panels[$panelFor[$tile['key']] ?? $tile['key']] = $tile['detail'];
        }
    }
    // Weather today: current conditions, today's all-day forecast, wind, and when it was read.
    $w = $f['weather'] ?? ['state' => 'nodata'];
    if ($w['state'] === 'ok') {
        $todayForecast = collect($f['week']['days'] ?? [])->first(fn (array $d): bool => $d['is_today'] ?? false);
        $panels['weather'] = [
            'title' => __('farmer.home.weather.title'),
            'rows' => array_filter([
                __('farmer.kpis.detail.conditions') => $w['condition'],
                __('farmer.kpis.detail.temperature') => number_format($w['temperature'], 0).'°C',
                __('farmer.kpis.detail.all_day') => trim(($todayForecast['condition'] ?? '').(($todayForecast['rain'] ?? null) ? ' · '.$todayForecast['rain'] : '')) ?: ($w['rain'] ?: null),
                __('farmer.kpis.detail.rain') => $w['rain'] ?: __('farmer.home.rain.none'),
                __('farmer.kpis.detail.wind') => $w['wind'] ?: __('farmer.kpis.detail.wind_calm'),
                __('farmer.kpis.detail.source') => $w['provider'] ?? null,
            ], fn ($v): bool => $v !== null && $v !== ''),
            'text' => ($w['stale'] ?? false) ? __('farmer.kpis.detail.weather_stale') : null,
            'updated_at' => $w['updated_at'],
            'link' => route('weather-predictions.index').'#weather',
        ];
    }
    // Barangay risk: the Barangay Risk card's rating (the risk tile, when shown, has the same one).
    if (($f['risk']['state'] ?? null) === 'ok' && ! isset($panels['risk'])) {
        $panels['risk'] = \App\Services\Farmer\FarmerHomeKpis::riskDetail($f['risk'], (string) ($f['barangay'] ?? ''));
    }
    // Expected harvest: the tile's tons when the farm size is known; otherwise the yield card's t/ha alone.
    $h = $f['harvest'] ?? ['state' => 'nodata'];
    if ($h['state'] === 'ok' && ! isset($panels['expected-harvest'])) {
        $panels['expected-harvest'] = [
            'title' => __('farmer.home.harvest.title'),
            'rows' => array_filter([
                __('farmer.kpis.detail.yield_per_ha') => number_format($h['yield'], 2).' t/ha',
                __('farmer.kpis.detail.season') => (string) ($h['season'] ?? ''),
                __('farmer.kpis.detail.compared') => (string) ($h['compare_label'] ?? ''),
                __('farmer.kpis.detail.size') => (string) ($h['size_note'] ?? ''),
            ], fn (string $v): bool => $v !== ''),
            'text' => __('farmer.kpis.detail.harvest_how'),
            'updated_at' => $h['ran_at'] ?? null,
            'link' => route('weather-predictions.index').'#yield',
        ];
    }
@endphp
@foreach ($panels as $key => $d)
    <template data-panel-template="{{ $key }}">
        <h2 class="fh-drawer-title" data-panel-title>{{ $d['title'] }}</h2>
        @if (! empty($d['badge']['label']))
            <p class="fh-risk-badge is-{{ $d['badge']['tone'] }}">{{ $d['badge']['label'] }}</p>
        @endif
        @if (! empty($d['rows']))
            <dl class="fh-drawer-facts">
                @foreach ($d['rows'] as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
        @endif
        @if (($d['progress'] ?? null) !== null)
            <div class="fh-drawer-progress">
                <span class="fh-kpi-bar" role="img" aria-label="{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}"><span style="width: {{ $d['progress'] }}%"></span></span>
                <span class="fh-muted">{{ __('farmer.kpis.crop.progress', ['percent' => $d['progress']]) }}</span>
            </div>
        @endif
        @if (! empty($d['text']))
            <p class="fh-words">{{ $d['text'] }}</p>
        @endif
        @if (! empty($d['updated_at']))
            <x-updated-at :at="$d['updated_at']" :label="__('farmer.home.updated')" />
        @endif
        @if (! empty($d['link']))
            <a class="fh-link fh-drawer-more" href="{{ $d['link'] }}">{{ __('farmer.kpis.detail.view_full') }} <span aria-hidden="true">→</span></a>
        @endif
    </template>
@endforeach
