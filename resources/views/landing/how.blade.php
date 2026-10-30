{{-- How it works (white): four numbered steps beside a screenshot of the Farmer Dashboard in a plain laptop frame.
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

            <ol data-reveal-group class="mt-10 flex flex-col" data-steps>
                @foreach ($steps as $step)
                    <li data-reveal class="relative flex gap-5 pb-8 last:pb-0">
                        @unless ($loop->last)
                            <span class="absolute bottom-0 left-5 top-10 w-px -translate-x-1/2 bg-line" aria-hidden="true"></span>
                        @endunless
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-brand text-base font-semibold text-white" aria-hidden="true">{{ $loop->iteration }}</span>
                        <div class="pt-1.5">
                            <h3 class="text-lg font-semibold leading-snug text-ink"><span class="sr-only">Step {{ $loop->iteration }}: </span>{{ $step['title'] }}</h3>
                            <p class="mt-1 text-base leading-relaxed text-muted">{{ $step['text'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>

        <figure data-reveal style="--reveal-delay: 80ms" data-dashboard-shot>
            <div class="rounded-t-xl border border-b-0 border-[#2A332C] bg-ink p-2 sm:p-3">
                <img src="{{ asset('images/landing/farmer-dashboard.jpg') }}" width="1280" height="480" loading="lazy" decoding="async"
                     alt="The Farmer Dashboard: a greeting, today’s advice, days to harvest, advisories in effect and days left in the season."
                     class="block h-auto w-full rounded-sm bg-surface">
            </div>
            <div class="mx-auto h-3 w-[104%] -translate-x-[2%] rounded-b-lg border border-line bg-[#E9ECE8]" aria-hidden="true"></div>
            <figcaption class="mt-4 text-center text-sm text-muted">The Farmer Dashboard, as a farmer sees it after logging in.</figcaption>
        </figure>
    </div>
</section>
