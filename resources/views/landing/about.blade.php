{{-- About (Figma: About). The fact cards are information only, so they have no hover state. --}}
@php
    $facts = [
        ['icon' => 'sun', 'title' => 'Made for rice', 'text' => 'Production analysis, yield prediction and planting advice for one crop, done properly.'],
        ['icon' => 'file-text', 'title' => 'Built on local records', 'text' => 'Forecasts use years of Lian’s own harvest and weather data, plus PAGASA and Open-Meteo.'],
        ['icon' => 'user-check', 'title' => 'Run by the MAO', 'text' => 'Kept current by MAO staff and technicians and maintained by IT personnel.'],
    ];
    $sources = ['PAGASA advisories', 'Open-Meteo forecasts', 'Stored climate records', 'MAO rice production records'];
@endphp
<section id="about" data-section aria-labelledby="about-title" class="lp-screen bg-white py-16 lg:py-20">
    <div class="lp-wrap">
        <div class="flex flex-col gap-12 lg:flex-row lg:items-start lg:gap-16">
            <div data-reveal class="flex min-w-0 flex-1 flex-col items-start gap-5">
                <p id="about-title" class="text-[0.9375rem] font-semibold leading-[1.4] text-brand">About iClimate</p>
                <h2 class="text-[1.625rem] font-medium leading-[1.25] tracking-[-0.02em] text-ink sm:text-[2.25rem]">
                    iClimate combines climate data with the MAO’s own rice production records, so farmers know what the weather means for their next planting and
                    <span class="text-muted">staff can see how climate and yield move together across barangays.</span>
                </h2>
                <a href="#contact" class="lp-btn-neutral mt-3" data-about-mao>About the MAO</a>
            </div>

            <div data-reveal style="--reveal-delay: 80ms" class="flex w-full shrink-0 flex-col gap-3.5 rounded-2xl bg-surface p-7 lg:w-[400px]">
                <h3 class="text-sm font-semibold leading-[1.4] text-brand">Our mission</h3>
                <p class="text-lg leading-[1.5] text-ink">To empower the farming community of Lian, Batangas through accurate climate information, clear analysis, and practical recommendations.</p>
                <hr class="border-line">
                <p class="text-[0.8125rem] leading-[1.4] text-muted">Information drawn from</p>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($sources as $source)
                        <li class="lp-tag bg-white">{{ $source }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <ul data-reveal-group class="mt-12 grid gap-4 md:grid-cols-3">
            @foreach ($facts as $fact)
                <li data-reveal class="rounded-lg border border-line bg-white p-6">
                    <x-icon :name="$fact['icon']" class="h-8 w-8 text-brand" stroke-width="1.5" />
                    <h3 class="mt-6 text-2xl font-semibold leading-[1.2] tracking-[-0.02em] text-ink">{{ $fact['title'] }}</h3>
                    <p class="mt-2 text-base leading-[1.4] text-muted">{{ $fact['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
