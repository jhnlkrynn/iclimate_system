{{-- "My Farm Today": six numbers a rice farmer checks first. Each one comes from the same source as its own page and links
     there; a number that is not available says so in words (never 0, never "null"). --}}
@php
    $w = $f['weather'];
    $wOk = $w['state'] === 'ok';
    $c = $f['condition'];
    $s = $f['schedule'];
    $r = $f['risk'];
    $h = $f['harvest'];
    $fmt = \App\Services\Analytics\FarmerKpiService::class;
    $weatherHref = route('weather-predictions.index', ['tab' => 'weather-forecast']);
    $statusIcon = ['good' => 'check', 'watch' => 'alert', 'danger' => 'danger', 'info' => 'info'];
@endphp
<section aria-labelledby="mfdTodayTitle" data-mfd-today>
    <h2 class="mfd-section-title" id="mfdTodayTitle">{{ __('farmer.today.title') }}</h2>
    <div class="mfd-kpis">

        {{-- 1. Weather now (Lian) --}}
        <article @class(['mfd-kpi', 'is-missing' => ! $wOk]) data-kpi="weather">
            <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => $wOk ? $w['icon'] : 'weather']){{ __('farmer.today.weather') }}</span>
            @if ($wOk)
                <span class="mfd-kpi-value"><span>{{ $fmt::formatTemperature($w['temperature']) }}</span><small>°C</small></span>
                <span class="mfd-kpi-headline">{{ $w['condition'] }}</span>
                @if ($w['feels_like'] !== null)<span class="mfd-kpi-meaning">{{ __('farmer.today.feels_like', ['temp' => $fmt::formatTemperature($w['feels_like'])]) }}</span>@endif
                <span class="mfd-kpi-source">{{ __('farmer.today.updated', ['time' => $w['updated_label'] ?? '—']) }}</span>
                <a class="mfd-kpi-link" href="{{ $weatherHref }}">{{ __('farmer.today.view_weather') }}</a>
            @else
                <span class="mfd-kpi-value is-word"><span>{{ __('farmer.today.updating') }}</span></span>
                <span class="mfd-kpi-meaning">{{ __('farmer.today.weather_updating') }}</span>
                <button type="button" class="mfd-btn is-quiet is-small" data-mfd-retry="weather">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            @endif
        </article>

        {{-- 2. Rain today (same reading) --}}
        @php $rainKnown = $wOk && ($w['rain_today'] !== null || $w['rain_chance'] !== null); @endphp
        <article @class(['mfd-kpi', 'is-missing' => ! $rainKnown, 'is-watch' => $rainKnown && ($w['levels']['rain'] ?? null) === 'heavy']) data-kpi="rain">
            <span class="mfd-kpi-top">
                <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'rain']){{ __('farmer.today.rain') }}</span>
                @if ($rainKnown && $w['rain_chance'] !== null)
                    @include('dashboards.farmer._learn', ['id' => 'mfdLearnChance', 'title' => __('farmer.learn.chance.title'),
                        'meaning' => __('farmer.learn.chance.'.$w['chance_level'], ['percent' => $fmt::formatPercent($w['rain_chance'])]), 'todo' => __('farmer.learn.chance.todo')])
                @endif
            </span>
            @if ($rainKnown)
                <span class="mfd-kpi-value">
                    @if ($w['rain_today'] !== null)<span>{{ $fmt::formatRain($w['rain_today']) }}</span><small>mm</small>@else<span>{{ $fmt::formatPercent($w['rain_chance']) }}</span><small>%</small>@endif
                </span>
                @if ($w['rain_today'] !== null && $w['rain_chance'] !== null)<span class="mfd-kpi-headline">{{ __('farmer.today.chance', ['percent' => $fmt::formatPercent($w['rain_chance'])]) }}</span>@endif
                <span class="mfd-kpi-meaning">{{ $w['rain_line'] }}</span>
                <a class="mfd-kpi-link" href="{{ $weatherHref }}">{{ __('farmer.today.view_weather') }}</a>
            @else
                <span class="mfd-kpi-value is-word"><span>{{ __('farmer.today.updating') }}</span></span>
                <span class="mfd-kpi-meaning">{{ __('farmer.today.weather_updating') }}</span>
                <a class="mfd-kpi-link" href="{{ $weatherHref }}">{{ __('farmer.today.view_weather') }}</a>
            @endif
        </article>

        {{-- 3. Farm condition (the most serious warning or risk rating shown below) --}}
        <article @class(['mfd-kpi', 'is-missing' => $c['value'] === null, 'is-'.($c['status'] ?? '') => $c['value'] !== null]) data-kpi="condition">
            <span class="mfd-kpi-top">
                <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'sprout']){{ __('farmer.today.condition') }}</span>
                @include('dashboards.farmer._learn', ['id' => 'mfdLearnCondition', 'title' => __('farmer.learn.condition.title'), 'meaning' => __('farmer.learn.condition.meaning'), 'todo' => __('farmer.learn.condition.todo')])
            </span>
            @if ($c['value'] !== null)
                <span class="mfd-kpi-value is-word">@include('dashboards.mao.icon', ['name' => $statusIcon[$c['status']]])<span>{{ $c['value'] }}</span></span>
                <span class="mfd-kpi-meaning">{{ $c['note'] }}</span>
            @else
                {{-- No rating is ever guessed: without weather or a barangay rating, the card gives the one safe thing to do. --}}
                <span class="mfd-kpi-value is-word">@include('dashboards.mao.icon', ['name' => 'info'])<span>{{ __('farmer.today.check_field') }}</span></span>
                <span class="mfd-kpi-meaning">{{ __('farmer.condition.missing') }}</span>
            @endif
        </article>

        {{-- 4. Planting status (the Calendar's planting schedule) --}}
        @php $g = $f['planting_guide'] ?? null; @endphp
        <article @class(['mfd-kpi', 'is-'.($g['tone'] ?? 'info') => $s['state'] !== 'ok' && $g]) data-kpi="planting">
            <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'calendar']){{ __('farmer.today.planting') }}</span>
            @if ($s['state'] === 'ok')
                @if ($s['planted'])
                    <span class="mfd-kpi-value is-word"><span>{{ __('farmer.today.day', ['day' => $s['day']]) }}</span></span>
                    @if ($s['stage'])<span class="mfd-kpi-headline">{{ __('farmer.today.stage', ['stage' => $s['stage']]) }}</span>@endif
                @else
                    <span class="mfd-kpi-value is-word"><span>{{ trans_choice('farmer.today.upcoming', $s['until_planting'], ['count' => $s['until_planting']]) }}</span></span>
                    <span class="mfd-kpi-headline">{{ $s['planting_date']->format('F j, Y') }}</span>
                @endif
                @if ($s['current'])
                    <span class="mfd-kpi-meaning">{{ __('farmer.today.next', ['activity' => $s['current']['label']]) }} • {{ __('farmer.schedule.now') }}</span>
                @elseif ($s['next'])
                    <span class="mfd-kpi-meaning">{{ __('farmer.today.next', ['activity' => $s['next']['label']]) }} • {{ $s['next']['start']->format('M j') }}</span>
                @endif
                <a class="mfd-kpi-link" href="{{ route('calendar.index') }}">{{ __('farmer.schedule.view') }}</a>
            @else
                {{-- No date saved yet: the Heat Map's planting advice for the barangay and this month's expected rain (dashboards/farmer/schedule.blade.php has the full guide). --}}
                <span class="mfd-kpi-value is-word">@include('dashboards.mao.icon', ['name' => $statusIcon[$g['tone']] ?? 'info'])<span>{{ $g['title'] }}</span></span>
                @if ($g['rain'])<span class="mfd-kpi-headline">{{ $g['rain'] }}</span>@endif
                <span class="mfd-kpi-meaning">{{ __('farmer.today.planting_why') }}</span>
                <a class="mfd-btn is-small" href="{{ route('farmer.planting-schedules.create') }}" data-mfd-popup="planting" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.today.add_planting') }}</a>
            @endif
        </article>

        {{-- 5. Barangay farm risk (the Heat Map's own rating) --}}
        <article @class(['mfd-kpi', 'is-missing' => $r['state'] !== 'ok', 'is-'.($r['tone'] ?? '') => $r['state'] === 'ok']) data-kpi="risk">
            <span class="mfd-kpi-top">
                <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'map']){{ __('farmer.today.risk') }}</span>
                @if ($r['state'] === 'ok')
                    @include('dashboards.farmer._learn', ['id' => 'mfdLearnRisk', 'title' => __('farmer.learn.risk.title'), 'meaning' => __('farmer.learn.risk.meaning'), 'todo' => __('farmer.learn.risk.todo')])
                @endif
            </span>
            @if ($r['state'] === 'ok')
                <span class="mfd-kpi-value is-word">@include('dashboards.mao.icon', ['name' => $statusIcon[$r['tone']] ?? 'info'])<span>{{ $r['level_full'] }}</span></span>
                <span class="mfd-kpi-headline">{{ $r['barangay'] }}</span>
                <span class="mfd-kpi-meaning">{{ $r['meaning'] }}</span>
                <a class="mfd-kpi-link" href="{{ route('heat-map.index') }}">{{ __('farmer.today.view_heat_map') }}</a>
            @elseif (in_array($r['state'], ['nobarangay', 'unverified'], true))
                <span class="mfd-kpi-value"><span>{{ $r['state'] === 'unverified' ? __('farmer.risk.needs_check') : __('farmer.my_farm.add.barangay') }}</span></span>
                <span class="mfd-kpi-meaning">{{ $r['state'] === 'unverified' ? __('farmer.risk.unverified', ['name' => $r['stored']]) : __('farmer.risk.no_barangay') }}</span>
                <a class="mfd-btn is-small" href="{{ route('profile.edit') }}" data-mfd-popup="profile" aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.risk.update_profile') }}</a>
            @else
                <span class="mfd-kpi-value is-word"><span>{{ __('farmer.today.updating') }}</span></span>
                <span class="mfd-kpi-meaning">{{ __('farmer.risk.no_rating') }} {{ __('farmer.risk.no_rating_reason') }}</span>
                <button type="button" class="mfd-btn is-quiet is-small" data-mfd-retry="risk">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
            @endif
        </article>

        {{-- 6. Rice yield forecast (the Predictions page's estimate) --}}
        @if ($h['state'] === 'loading')
            <article class="mfd-kpi is-skeleton" data-kpi="yield" data-mfd-load="harvest" aria-busy="true">
                <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'yield']){{ __('farmer.today.yield') }}</span>
                <div class="mfd-skel mfd-skel-big"></div><div class="mfd-skel mfd-skel-short"></div>
                <span class="mfd-sr-only" role="status">{{ __('farmer.state.loading') }}</span>
            </article>
        @elseif ($h['state'] === 'ok')
            <article class="mfd-kpi" data-kpi="yield">
                <span class="mfd-kpi-top">
                    <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'yield']){{ __('farmer.today.yield') }}</span>
                    @include('dashboards.farmer._learn', ['id' => 'mfdLearnYield', 'title' => __('farmer.learn.yield.title'), 'meaning' => __('farmer.learn.yield.meaning'), 'todo' => __('farmer.learn.yield.todo')])
                </span>
                <span class="mfd-kpi-value"><span>{{ number_format($h['yield'], 2) }}</span><small>{{ __('farmer.harvest.unit') }}</small></span>
                <span class="mfd-kpi-headline">{{ __('farmer.harvest.expected') }}</span>
                @if ($h['production'] !== null)
                    <span class="mfd-kpi-meaning">{{ __('farmer.today.yield_total', ['tons' => number_format($h['production'], 1), 'area' => number_format($h['area'], 2)]) }}</span>
                @endif
                <a class="mfd-kpi-link" href="{{ route('weather-predictions.index', ['tab' => 'rice-yield']) }}">{{ __('farmer.today.view_prediction') }}</a>
            </article>
        @else
            {{-- No estimate this season yet: the latest real harvest record (the barangay's, else Lian's usual for this month), labelled as a record, never as this season's forecast. --}}
            @php
                $past = $f['production'];
                $usual = $f['usual_yield'] ?? null;
            @endphp
            <article @class(['mfd-kpi', 'is-missing' => ! $past && ! $usual]) data-kpi="yield">
                <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'yield']){{ __('farmer.today.yield') }}</span>
                @if ($past)
                    <span class="mfd-kpi-value"><span>{{ number_format($past['yield'], 2) }}</span><small>{{ __('farmer.harvest.unit') }}</small></span>
                    <span class="mfd-kpi-headline">{{ __('farmer.today.yield_past', ['barangay' => $f['barangay'], 'when' => $past['year']]) }}</span>
                @elseif ($usual)
                    <span class="mfd-kpi-value"><span>{{ number_format($usual['historical'], 2) }}</span><small>{{ __('farmer.harvest.unit') }}</small></span>
                    <span class="mfd-kpi-headline">{{ $usual['same_month'] ? __('farmer.today.yield_usual', ['month' => $usual['month_name']]) : __('farmer.today.yield_usual_all') }}</span>
                @else
                    <span class="mfd-kpi-value is-word"><span>{{ __('farmer.today.updating') }}</span></span>
                @endif
                <span class="mfd-kpi-meaning">{{ $h['reason'] }}</span>
                @if ($h['missing'] !== [])
                    <a class="mfd-btn is-small" href="{{ route('profile.edit') }}" data-mfd-popup="farm-size" data-mfd-popup-variant="info" data-mfd-popup-title="{{ __('farmer.quick.farm.popup_title') }}" data-mfd-popup-intro="{{ __('farmer.quick.farm.popup_intro') }}"
                        aria-haspopup="dialog" data-fast-nav-bound="true">{{ __('farmer.harvest.add_area') }}</a>
                @else
                    <a class="mfd-kpi-link" href="{{ route('rice-productions.index') }}">{{ __('farmer.production.view') }}</a>
                @endif
            </article>
        @endif
    </div>
</section>
