<x-app-layout>
    @php
        $renderSection = fn (string $name): string => view('dashboards.farmer.'.$name, ['f' => $f])->render();
    @endphp

    <div class="mfd" data-farmer-dashboard data-live-url="{{ route('farmer.dashboard.live') }}"
        data-age-now="{{ __('farmer.header.js_now') }}" data-age-minute="{{ __('farmer.header.js_minute') }}" data-age-minutes="{{ __('farmer.header.js_minutes') }}"
        data-age-hour="{{ __('farmer.header.js_hour') }}" data-age-hours="{{ __('farmer.header.js_hours') }}"
        data-stale-after="{{ (int) config('farmer_dashboard.weather.stale_after_minutes', 60) }}"
        data-text-refreshing="{{ __('farmer.header.refreshing') }}" data-text-refresh="{{ __('farmer.header.refresh') }}"
        data-text-failed="{{ __('farmer.state.refresh_failed') }}" data-text-offline="{{ __('farmer.state.offline') }}" data-text-seeing="{{ __('farmer.state.seeing_from') }}"
        data-text-updated="{{ __('farmer.state.updated_announce') }}" data-text-old="{{ __('farmer.header.old_data') }}"
        data-font="Inter">

        <nav class="mfd-tabs" aria-label="Farmer dashboard sections">
            <a class="mfd-tab @if (($tab ?? 'overview') === 'overview') is-active @endif"
                href="{{ route('farmer.dashboard', ['tab' => 'overview']) }}"
                @if (($tab ?? 'overview') === 'overview') aria-current="page" @endif>Overview</a>
            <a class="mfd-tab @if (($tab ?? 'overview') === 'heatmap') is-active @endif"
                href="{{ route('farmer.dashboard', ['tab' => 'heatmap']) }}"
                @if (($tab ?? 'overview') === 'heatmap') aria-current="page" @endif>Heat Map</a>
        </nav>

        @if (($tab ?? 'overview') === 'heatmap')
            <section class="mfd-card mfd-map-page" aria-labelledby="farmerHeatMapTitle">
                <div class="mfd-card-head">
                    <div>
                        <h1 id="farmerHeatMapTitle" class="mfd-map-section-label">LIAN MUNICIPALITY RISK MAP</h1>
                    </div>
                </div>
                @include('partials.barangay-risk-map', [
                    'heading' => 'Lian Municipality Risk Map',
                    'subheading' => 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.',
                    'showRefresh' => false,
                    'highlightBarangay' => $f['barangay'] ? \App\Support\LianBarangays::canonicalName($f['barangay']) : null,
                ])
            </section>
        @else
        {{-- Read out by screen readers after a refresh; the sections themselves are not live, so a refresh does not re-read the page. --}}
        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden>
            <span data-mfd-refresh-error-text>{{ __('farmer.state.refresh_failed') }}</span>
            <button type="button" class="mfd-btn is-quiet" data-mfd-retry="all">{{ __('farmer.state.retry') }}</button>
        </div>

        {{-- Page order is reading order for everyone (screen readers and keyboard included): what to do today comes before the numbers. --}}
        @foreach (['header', 'today', 'kpis', 'week', 'season', 'harvest', 'rain', 'risk'] as $name)
            @php $html = $renderSection($name); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        <details class="mfd-map" id="farmRiskMap">
            <summary>{{ __('farmer.risk.map_summary') }}</summary>
            <div class="mfd-map-body">
                <p class="mfd-foot" style="margin-top:0">{{ __('farmer.risk.map_note') }}</p>
                @include('partials.barangay-risk-map', [
                    'heading' => 'Lian Municipality Risk Map',
                    'subheading' => 'All 19 barangays at a glance, using the same live weather and recorded rice yield data used municipality-wide. Click any barangay for details.',
                    'showRefresh' => false,
                    'highlightBarangay' => $f['barangay'] ? \App\Support\LianBarangays::canonicalName($f['barangay']) : null,
                ])
            </div>
        </details>

        @foreach (['setup', 'advisories'] as $name)
            @php $html = $renderSection($name); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        <section class="mfd-card mfd-quickwrap" aria-labelledby="mfdQuickTitle">
            <div class="mfd-card-head"><h2 id="mfdQuickTitle">{{ __('farmer.quick.title') }}</h2></div>
            <div class="mfd-quick">
                <a href="{{ route('planting-advisories.index') }}">@include('dashboards.mao.icon', ['name' => 'advisory']){{ __('farmer.quick.advisories') }}</a>
                <a href="{{ route('calendar.index') }}">@include('dashboards.mao.icon', ['name' => 'calendar']){{ __('farmer.quick.calendar') }}</a>
                <a href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">@include('dashboards.mao.icon', ['name' => 'yield']){{ __('farmer.quick.prediction') }}</a>
                <a href="{{ route('notifications.index') }}">@include('dashboards.mao.icon', ['name' => 'bell']){{ __('farmer.quick.notifications') }} @if ($f['unread'] > 0)<span class="mfd-badge" aria-label="{{ trans_choice('farmer.quick.unread', $f['unread'], ['count' => $f['unread']]) }}">{{ $f['unread'] }}</span>@endif</a>
            </div>
        </section>

        <details class="mfd-info">
            <summary>@include('dashboards.mao.icon', ['name' => 'info']){{ __('farmer.about.title') }}</summary>
            <div class="mfd-info-panel">
                <ul>
                    @foreach (['weather', 'coming', 'rain', 'harvest', 'advisories'] as $item)
                        <li><strong>{{ __('farmer.about.'.$item.'.label') }}</strong> {{ __('farmer.about.'.$item.'.text') }}</li>
                    @endforeach
                </ul>
            </div>
        </details>
        @endif
    </div>
</x-app-layout>
