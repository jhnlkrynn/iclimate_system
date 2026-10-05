<x-app-layout>
    @php
        $renderSection = fn (string $name): string => view('dashboards.farmer.'.$name, ['f' => $f])->render();
    @endphp

    <div class="mfd" data-farmer-dashboard data-live-url="{{ route('farmer.dashboard.live') }}" lang="{{ $f['locale'] ?? app()->getLocale() }}"
        data-age-now="{{ __('farmer.header.js_now') }}" data-age-minute="{{ __('farmer.header.js_minute') }}" data-age-minutes="{{ __('farmer.header.js_minutes') }}"
        data-age-hour="{{ __('farmer.header.js_hour') }}" data-age-hours="{{ __('farmer.header.js_hours') }}"
        data-stale-after="{{ (int) config('farmer_dashboard.weather.stale_after_minutes', 60) }}"
        data-text-refreshing="{{ __('farmer.header.refreshing') }}" data-text-refresh="{{ __('farmer.header.refresh') }}"
        data-text-failed="{{ __('farmer.state.refresh_failed') }}" data-text-offline="{{ __('farmer.state.offline') }}" data-text-seeing="{{ __('farmer.state.seeing_from') }}"
        data-text-updated="{{ __('farmer.state.updated_announce') }}" data-text-old="{{ __('farmer.header.old_data') }}"
        data-text-language="{{ __('farmer.header.language_changing') }}" data-text-language-failed="{{ __('farmer.state.language_failed') }}"
        data-font="Inter">

        {{-- Read out by screen readers after a refresh; the sections themselves are not live, so a refresh does not re-read the page. --}}
        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden>
            <span data-mfd-refresh-error-text>{{ __('farmer.state.refresh_failed') }}</span>
            <button type="button" class="mfd-btn is-quiet" data-mfd-retry="all">{{ __('farmer.state.retry') }}</button>
        </div>

        {{-- The farmer's daily home page, a summary of the other Farmer pages. Page order is reading order:
             1 header, 2 the numbers, 3 today's highlights, 4 weather + 5 schedule, 6 barangay risk + 7 yield forecast,
             8 advice + 9 my farm, 10 reminders and updates, 11 quick actions. Each section opens its own page for detail. --}}
        @foreach (['header', 'today', 'highlights', 'weather', 'schedule', 'risk', 'harvest', 'advisories', 'farm', 'updates', 'quick'] as $name)
            @php $html = $renderSection($name); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        @include('dashboards.farmer.popups')
        @endif

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
