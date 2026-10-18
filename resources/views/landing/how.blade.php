{{-- How it works (Figma: How it works): four steps joined by a line, no numbers. --}}
@php
    $steps = [
        ['icon' => 'download-cloud', 'title' => 'Climate data comes in', 'text' => 'Real-time and historical data from trusted sources like PAGASA.'],
        ['icon' => 'bell', 'title' => 'Advisories go out', 'text' => 'The data is reviewed into planting and fertilizer recommendations.'],
        ['icon' => 'alert-triangle', 'title' => 'Risks are watched', 'text' => 'Weather risks and climate patterns that may affect crops are tracked.'],
        ['icon' => 'file-text', 'title' => 'Results are reported', 'text' => 'Reports and yield forecasts guide the next season’s decisions.'],
    ];
@endphp
<section id="how" data-section aria-labelledby="how-title" class="lp-screen bg-white py-16 lg:py-20">
    <div class="lp-wrap">
        <h2 id="how-title" data-reveal class="text-[2rem] font-bold leading-[1.2] tracking-[-0.03em] text-ink sm:text-[2.5rem]">How it works</h2>
        <ol data-reveal-group class="mt-14 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 lg:gap-0">
            @foreach ($steps as $step)
                <li data-reveal class="lg:pr-7">
                    <div class="flex items-center gap-3">
                        <x-icon :name="$step['icon']" class="h-[22px] w-[22px] text-brand" />
                        <span class="h-px flex-1 bg-line" aria-hidden="true" data-reveal-line></span>
                    </div>
                    <h3 class="mt-6 text-lg font-semibold leading-[1.4] text-ink">{{ $step['title'] }}</h3>
                    <p class="mt-2 text-base leading-[1.4] text-muted">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </div>
</section>
