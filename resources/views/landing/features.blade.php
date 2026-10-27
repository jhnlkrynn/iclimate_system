{{-- Features (Figma: Features). Cards describe signed-in tools; none of them is a link, so none has a hover or pointer. --}}
@php
    $features = [
        ['title' => 'Rice production', 'text' => 'Local production records and yield forecasts for planning and food security.', 'tag' => 'Production records'],
        ['title' => 'Planting advisory', 'text' => 'When to plant, based on the season, rainfall outlook and soil conditions.', 'tag' => 'Advisories'],
        ['title' => 'Climate monitoring', 'text' => 'Long-term trends and unusual patterns that affect farming.', 'tag' => 'Monitoring'],
        ['title' => 'Heat map', 'text' => 'Flood, drought, typhoon and heat risk for each barangay.', 'tag' => 'Risk records'],
        ['title' => 'Reports', 'text' => 'Seasonal reports and yield forecasts you can download.', 'tag' => 'Analytics', 'wide' => true],
    ];
@endphp
<section id="features" data-section aria-labelledby="features-title" class="lp-screen py-16 lg:py-20">
    <div class="lp-wrap"><div class="lp-panel">
        <div data-reveal class="flex flex-col gap-4 lg:flex-row lg:items-end lg:gap-10">
            <h2 id="features-title" class="lp-h2 lg:w-[560px] lg:shrink-0">Everything for the rice season</h2>
            <p class="text-[1.0625rem] leading-[1.4] text-muted">Plan, monitor and decide from planting through harvest with the same data the MAO uses.</p>
        </div>

        <div data-reveal-group class="mt-10 grid gap-4 lg:grid-cols-2">
            <article data-reveal class="flex flex-col gap-5 rounded-[14px] border border-line bg-white p-6 sm:p-7">
                <h3 class="text-[1.375rem] font-semibold leading-[1.2] tracking-[-0.01em] text-ink">Weather analysis</h3>
                <p class="text-base leading-[1.4] text-muted">Real-time and historical rainfall, temperature, humidity and wind for Lian, including PAGASA data.</p>
                <span class="lp-tag self-start">Climate records</span>
                {{-- TODO: replace this live reading with a screenshot of the weather dashboard once one is available. --}}
                <div class="flex flex-1 flex-col justify-center gap-4 rounded-[10px] bg-tint p-5" data-weather>
                    <p class="text-sm leading-[1.4] text-muted" data-weather-status>{{ $heroClimate ? 'Latest saved climate record for Lian' : 'Checking current weather in Lian…' }}</p>
                    <dl class="grid grid-cols-2 gap-3">
                        <div class="rounded-lg bg-white p-4">
                            <dt class="flex items-center gap-2 text-sm text-muted"><x-icon name="thermometer" class="h-4 w-4 text-brand" />Temperature</dt>
                            <dd class="mt-1 text-2xl font-semibold text-ink" data-weather-temp>{{ $heroClimate ? round((float) $heroClimate->temperature).'°C' : '--' }}</dd>
                        </div>
                        <div class="rounded-lg bg-white p-4">
                            <dt class="flex items-center gap-2 text-sm text-muted"><x-icon name="droplet" class="h-4 w-4 text-brand" />Humidity</dt>
                            <dd class="mt-1 text-2xl font-semibold text-ink" data-weather-humidity>{{ $heroClimate ? round((float) $heroClimate->humidity).'%' : '--' }}</dd>
                        </div>
                        <div class="rounded-lg bg-white p-4">
                            {{-- A stored ClimateRecord's wind unit is not established, so no unit is shown for it (live readings are km/h). --}}
                            <dt class="flex items-center gap-2 text-sm text-muted"><x-icon name="wind" class="h-4 w-4 text-brand" />Wind speed</dt>
                            <dd class="mt-1 text-2xl font-semibold text-ink" data-weather-wind>{{ $heroClimate ? round((float) $heroClimate->wind_speed) : '--' }}</dd>
                        </div>
                        <div class="rounded-lg bg-white p-4">
                            <dt class="flex items-center gap-2 text-sm text-muted"><x-icon name="cloud-rain" class="h-4 w-4 text-brand" /><span data-weather-rain-label>{{ $heroClimate ? 'Rainfall (record)' : 'Rainfall today' }}</span></dt>
                            <dd class="mt-1 text-2xl font-semibold text-ink" data-weather-rain>{{ $heroClimate ? number_format((float) $heroClimate->rainfall, 1).' mm' : '--' }}</dd>
                        </div>
                    </dl>
                </div>
            </article>

            <ul class="grid gap-4 sm:grid-cols-2">
                @foreach ($features as $feature)
                    <li data-reveal class="flex flex-col items-start gap-2 rounded-[14px] border border-line bg-white p-6 sm:p-7 {{ ! empty($feature['wide']) ? 'sm:col-span-2' : '' }}">
                        <h3 class="text-[1.1875rem] font-semibold leading-[1.2] tracking-[-0.02em] text-ink">{{ $feature['title'] }}</h3>
                        <p class="text-base leading-[1.4] text-muted">{{ $feature['text'] }}</p>
                        <span class="lp-tag mt-auto">{{ $feature['tag'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div></div>
</section>
