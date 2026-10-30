{{-- How it works (white): four steps, numbered 01 to 04 because their order matters, beside a screenshot of the Farmer
     Dashboard in a plain laptop frame.
     The screenshot (public/images/landing/farmer-dashboard.jpg) shows a made-up test farmer, never a real account. --}}
@php
    $steps = [
        ['title' => 'Climate data comes in', 'text' => 'Real-time and historical data from trusted sources like PAGASA.'],
        ['title' => 'Advisories go out', 'text' => 'The data is reviewed into planting and fertilizer recommendations.'],
        ['title' => 'Risks are watched', 'text' => 'Weather risks and climate patterns that may affect crops are tracked.'],
        ['title' => 'Results are reported', 'text' => 'Reports and yield forecasts guide the next season’s decisions.'],
    ];
@endphp
<section id="how" data-section aria-labelledby="how-title" class="lp-section">
    <div class="lp-wrap grid gap-12 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)] lg:items-center lg:gap-16">
        <div>
            <x-landing-heading id="how-title" eyebrow="How it works" lead="From the weather data to the advice on your phone, in four steps.">
                From the forecast<br class="max-sm:hidden"> to your field
            </x-landing-heading>

            <ol data-reveal-group class="mt-10 flex flex-col gap-6" data-steps>
                @foreach ($steps as $step)
                    <li data-reveal class="flex gap-5 border-t border-line pt-5">
                        <span class="w-8 shrink-0 pt-0.5 text-sm font-semibold tabular-nums text-muted" aria-hidden="true" data-step-number>{{ sprintf('%02d', $loop->iteration) }}</span>
                        <div>
                            <h3 class="lp-card-title"><span class="sr-only">Step {{ $loop->iteration }}: </span>{{ $step['title'] }}</h3>
                            <p class="mt-1 text-base leading-relaxed text-muted">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <figure data-reveal style="--reveal-delay: 80ms" data-dashboard-shot>
            <div class="rounded-t-xl border border-b-0 border-sage-900 bg-ink p-2 sm:p-3">
                <img src="{{ asset('images/landing/farmer-dashboard.jpg') }}" width="1280" height="480" loading="lazy" decoding="async"
                     alt="The Farmer Dashboard: a greeting, today’s advice, days to harvest, advisories in effect and days left in the season."
                     class="block h-auto w-full rounded-sm bg-surface">
            </div>
            <div class="mx-auto h-3 w-[104%] -translate-x-[2%] rounded-b-lg border border-line bg-sage-100" aria-hidden="true"></div>
            <figcaption class="mt-4 text-center text-sm text-muted">The Farmer Dashboard, as a farmer sees it after logging in.</figcaption>
        </figure>
    </div>
</section>
