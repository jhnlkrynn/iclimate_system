{{-- Features (surface): six signed-in tools, an icon, a title and one line each, in equal-height cards. None of them is
     a link, so none has a hover or pointer. --}}
@php
    $features = [
        ['icon' => 'cloud-sun', 'title' => 'Weather analysis', 'text' => 'Rainfall, temperature, humidity and wind for Lian, now and in past years.'],
        ['icon' => 'sprout', 'title' => 'Rice production', 'text' => 'Local production records and yield forecasts for planning and food security.'],
        ['icon' => 'calendar', 'title' => 'Planting advisory', 'text' => 'When to plant, based on the season, rainfall outlook and soil conditions.'],
        ['icon' => 'activity', 'title' => 'Climate monitoring', 'text' => 'Long-term trends and unusual patterns that affect farming.'],
        ['icon' => 'map', 'title' => 'Heat map', 'text' => 'Flood, drought, typhoon and heat risk for each barangay.'],
        ['icon' => 'bar-chart', 'title' => 'Reports', 'text' => 'Seasonal reports and yield forecasts you can download.'],
    ];
@endphp
<section id="features" data-section aria-labelledby="features-title" class="lp-section lp-section-surface">
    <div class="lp-wrap">
        <x-landing-heading id="features-title" eyebrow="Features" lead="Plan, monitor and decide from planting through harvest with the same data the MAO uses.">
            Everything for<br class="max-sm:hidden"> the rice season
        </x-landing-heading>

        <ul data-reveal-group class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" data-feature-grid>
            @foreach ($features as $feature)
                <li data-reveal class="lp-card flex flex-col" data-feature>
                    <span class="lp-icon-circle"><x-icon :name="$feature['icon']" class="h-5 w-5" /></span>
                    <h3 class="mt-5 text-lg font-semibold text-ink">{{ $feature['title'] }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-muted">{{ $feature['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
