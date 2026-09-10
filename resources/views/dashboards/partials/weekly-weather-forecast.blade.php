@php
    $forecast = $forecast ?? [];
    $days = $forecast['days'] ?? [];
    $summary = $forecast['summary'] ?? [];
    $location = $forecast['location'] ?? [];
    $fetchedAt = $forecast['fetched_at'] ?? null;
    $updatedLabel = $fetchedAt ? \Illuminate\Support\Carbon::parse($fetchedAt)->timezone($location['timezone'] ?? config('services.weather.timezone', 'Asia/Manila'))->format('M j, Y g:i A') : 'Not updated yet';
    $sourceLabel = ($forecast['source_name'] ?? 'Open-Meteo Forecast API').' for '.($location['name'] ?? 'Lian').', '.($location['province'] ?? 'Batangas');
    $note = $forecast['note'] ?? null;
@endphp

@once
    <style>
        .weekly-weather {
            border: 1px solid rgba(95,143,120,.3);
            border-radius: 8px;
            background: #fff;
            overflow: hidden;
        }
        .weekly-weather-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1rem .85rem;
            border-bottom: 1px solid rgba(95,143,120,.18);
            background: #f7fbf8;
        }
        .weekly-weather-title { margin: 0; color: #1f4d3a; font-size: 1.08rem; font-weight: 800; line-height: 1.3; }
        .weekly-weather-sub { margin: .2rem 0 0; color: #647067; font-size: .88rem; line-height: 1.45; }
        .weekly-weather-badge {
            flex: 0 0 auto;
            display: inline-flex;
            align-items: center;
            border: 1px solid rgba(22,119,184,.24);
            border-radius: 999px;
            background: #eef7ff;
            color: #155a84;
            padding: .35rem .62rem;
            font-size: .76rem;
            font-weight: 800;
            white-space: nowrap;
        }
        .weekly-weather-body { padding: 1rem; }
        .weekly-weather-summary {
            display: grid;
            grid-template-columns: minmax(220px, .95fr) minmax(0, 1.8fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }
        .weekly-weather-summary-main {
            border: 1px solid rgba(95,143,120,.24);
            border-radius: 8px;
            background: #fff;
            padding: .9rem;
        }
        .weekly-weather-summary-main strong { display: block; color: #1f2937; font-size: 1rem; line-height: 1.35; }
        .weekly-weather-summary-main span { display: block; margin-top: .35rem; color: #647067; font-size: .84rem; line-height: 1.45; }
        .weekly-weather-advice {
            display: grid;
            gap: .5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        .weekly-weather-advice li {
            border-left: 3px solid #5f8f78;
            background: #f7fbf8;
            border-radius: 6px;
            padding: .55rem .7rem;
            color: #334155;
            font-size: .88rem;
            line-height: 1.45;
        }
        .weekly-weather-scroll { overflow-x: auto; padding-bottom: .25rem; -webkit-overflow-scrolling: touch; }
        .weekly-weather-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(138px, 1fr));
            gap: .7rem;
            min-width: 980px;
        }
        .weekly-weather-day {
            display: flex;
            flex-direction: column;
            gap: .55rem;
            min-height: 255px;
            border: 1px solid rgba(95,143,120,.28);
            border-radius: 8px;
            background: #fff;
            padding: .78rem;
        }
        .weekly-weather-date { display: flex; align-items: center; justify-content: space-between; gap: .5rem; }
        .weekly-weather-date strong { color: #1f2937; font-size: .9rem; line-height: 1.2; }
        .weekly-weather-risk {
            border-radius: 999px;
            padding: .18rem .44rem;
            font-size: .68rem;
            font-weight: 800;
            line-height: 1.2;
            color: #1f4d3a;
            background: rgba(95,143,120,.14);
        }
        .weekly-weather-risk.moderate { color: #946200; background: #fff4d8; }
        .weekly-weather-risk.high,
        .weekly-weather-risk.severe { color: #a23324; background: #fff0ea; }
        .weekly-weather-icon-row { display: flex; align-items: center; gap: .55rem; min-height: 42px; }
        .weekly-weather-icon-row img { width: 38px; height: 38px; flex: 0 0 auto; }
        .weekly-weather-condition { color: #1f4d3a; font-size: .9rem; font-weight: 800; line-height: 1.25; }
        .weekly-weather-metrics {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: .4rem;
        }
        .weekly-weather-metric {
            border-radius: 7px;
            background: #f7fbf8;
            padding: .45rem;
            min-height: 56px;
        }
        .weekly-weather-metric span { display: block; color: #647067; font-size: .68rem; font-weight: 800; line-height: 1.2; text-transform: uppercase; }
        .weekly-weather-metric strong { display: block; margin-top: .12rem; color: #1f2937; font-size: .84rem; line-height: 1.25; }
        .weekly-weather-rec { margin-top: auto; color: #475569; font-size: .8rem; line-height: 1.4; }
        .weekly-weather-note { margin: .75rem 0 0; color: #647067; font-size: .78rem; line-height: 1.45; }
        @media (max-width: 1199.98px) {
            .weekly-weather-summary { grid-template-columns: 1fr; }
            .weekly-weather-grid { grid-template-columns: repeat(7, minmax(150px, 1fr)); min-width: 1080px; }
        }
        @media (max-width: 767.98px) {
            .weekly-weather-head { flex-direction: column; padding: .9rem; gap: .55rem; }
            .weekly-weather-badge { white-space: normal; }
            .weekly-weather-body { padding: .85rem; }
            .weekly-weather-grid { display: flex; min-width: 0; gap: .65rem; scroll-snap-type: x mandatory; }
            .weekly-weather-day { flex: 0 0 min(82vw, 280px); scroll-snap-align: start; }
            .weekly-weather-summary-main, .weekly-weather-advice li { padding: .7rem; }
        }
    </style>
@endonce

<section class="weekly-weather" aria-labelledby="weeklyWeatherForecastTitle">
    <div class="weekly-weather-head">
        <div>
            <h2 class="weekly-weather-title" id="weeklyWeatherForecastTitle">Weekly Weather Forecast</h2>
            <p class="weekly-weather-sub">{{ $sourceLabel }}. Updated {{ $updatedLabel }}.</p>
        </div>
        <span class="weekly-weather-badge">{{ ($forecast['stale'] ?? false) ? 'Latest cached forecast' : 'Open-Meteo 7-day outlook' }}</span>
    </div>
    <div class="weekly-weather-body">
        <div class="weekly-weather-summary">
            <div class="weekly-weather-summary-main">
                <strong>{{ $summary['headline'] ?? 'Review the 7-day outlook before field work.' }}</strong>
                <span>Advice considers the full week for planting, harvesting, irrigation, flood risk, and wind warnings.</span>
            </div>
            <ul class="weekly-weather-advice">
                @forelse(($summary['advice'] ?? []) as $advice)
                    <li>{{ $advice }}</li>
                @empty
                    <li>No weekly advice is available yet. Check again after the forecast updates.</li>
                @endforelse
            </ul>
        </div>

        @if($days !== [])
            <div class="weekly-weather-scroll" tabindex="0" aria-label="Seven day weather forecast cards">
                <div class="weekly-weather-grid">
                    @foreach($days as $day)
                        <article class="weekly-weather-day">
                            <div class="weekly-weather-date">
                                <strong>{{ $day['label'] ?? $day['date'] ?? 'Forecast' }}</strong>
                                <span class="weekly-weather-risk {{ strtolower($day['risk_level'] ?? 'low') }}">{{ $day['risk_level'] ?? 'Low' }}</span>
                            </div>
                            <div class="weekly-weather-icon-row">
                                <img src="{{ $day['icon'] ?? '/images/weather/unavailable.svg' }}" alt="{{ $day['condition'] ?? 'Weather condition' }}">
                                <div class="weekly-weather-condition">{{ $day['condition'] ?? 'N/A' }}</div>
                            </div>
                            <div class="weekly-weather-metrics">
                                <div class="weekly-weather-metric"><span>Temp</span><strong>{{ isset($day['temperature_max_c'], $day['temperature_min_c']) ? number_format((float) $day['temperature_min_c'], 0).' - '.number_format((float) $day['temperature_max_c'], 0).'&deg;C' : 'N/A' }}</strong></div>
                                <div class="weekly-weather-metric"><span>Rain</span><strong>{{ isset($day['rainfall_mm']) ? number_format((float) $day['rainfall_mm'], 1).' mm' : 'N/A' }}</strong></div>
                                <div class="weekly-weather-metric"><span>Chance</span><strong>{{ isset($day['precipitation_probability']) ? number_format((float) $day['precipitation_probability'], 0).'%' : 'N/A' }}</strong></div>
                                <div class="weekly-weather-metric"><span>Humidity</span><strong>{{ isset($day['humidity_percent']) ? number_format((float) $day['humidity_percent'], 0).'%' : 'N/A' }}</strong></div>
                                <div class="weekly-weather-metric"><span>Wind</span><strong>{{ isset($day['wind_speed_kmh']) ? number_format((float) $day['wind_speed_kmh'], 1).' km/h' : 'N/A' }}</strong></div>
                                <div class="weekly-weather-metric"><span>Risk</span><strong>{{ $day['risk_level'] ?? 'N/A' }}</strong></div>
                            </div>
                            <p class="weekly-weather-rec">{{ $day['recommendation'] ?? 'Check iClimate advisories before field work.' }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        @else
            <div class="weekly-weather-summary-main">
                <strong>No weekly forecast is available yet.</strong>
                <span>The dashboard will show the latest cached forecast if the online API is temporarily unavailable.</span>
            </div>
        @endif

        @if($note)
            <p class="weekly-weather-note">{{ $note }}</p>
        @endif
    </div>
</section>
