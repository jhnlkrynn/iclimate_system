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

        {{-- Read out by screen readers after a refresh; the sections themselves are not live, so a refresh does not re-read the page. --}}
        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden>
            <span data-mfd-refresh-error-text>{{ __('farmer.state.refresh_failed') }}</span>
            <button type="button" class="mfd-btn is-quiet" data-mfd-retry="all">{{ __('farmer.state.retry') }}</button>
        </div>

        {{-- Page order is reading order for everyone (screen readers and keyboard included): the farm, today, the week,
             the yield forecast and the latest advice come first; the detail a farmer asks for less often is folded away. --}}
        @foreach (['header', 'farm', 'today', 'week', 'harvest', 'advisories', 'season', 'setup', 'quick'] as $name)
            @php $html = $renderSection($name); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        <details class="mfd-map mfd-extra">
            <summary>{{ __('farmer.more.summary') }}</summary>
            <div class="mfd-map-body mfd-extra-body">
                @foreach (['kpis', 'rain', 'risk'] as $name)
                    @php $html = $renderSection($name); @endphp
                    <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
                @endforeach
            </div>
        </details>

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

        @include('dashboards.farmer.popups')

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
    </div>

    {{-- "View Advisory" opens the advisory here, in the same pop-up as the Advisories page. --}}
    @include('partials.fad-modal-shell')
</x-app-layout>
