{{-- About (white). The fact cards are information only, so they have no hover state. --}}
@php
    $facts = [
        ['icon' => 'sun', 'title' => 'Made for rice', 'text' => 'Production analysis, yield prediction and planting advice for one crop, done properly.'],
        ['icon' => 'file-text', 'title' => 'Built on local records', 'text' => 'Forecasts use years of Lian’s own harvest and weather data, plus PAGASA and Open-Meteo.'],
        ['icon' => 'user-check', 'title' => 'Run by the MAO', 'text' => 'Kept current by MAO Staff and maintained by IT Personnel.'],
    ];
    $sources = ['PAGASA advisories', 'Open-Meteo forecasts', 'Stored climate records', 'MAO rice production records'];
@endphp
<section id="about" data-section aria-labelledby="about-title" class="lp-section">
    <div class="lp-wrap">
        <div class="grid gap-10 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-16">
            <div class="flex min-w-0 flex-col items-start">
                <x-landing-heading id="about-title" eyebrow="About">
                    Climate data and local records,<br class="max-sm:hidden"> in one place
                </x-landing-heading>
                <p data-reveal class="lp-lead">
                    iClimate combines climate data with the MAO’s own rice production records, so farmers know what the weather means for their next
                    planting and staff can see how climate and yield move together across barangays.
                </p>
                <a href="#contact" data-reveal class="lp-btn-neutral mt-8" data-about-mao>About the MAO</a>
            </div>

            <div data-reveal style="--reveal-delay: 80ms" class="flex flex-col gap-4 rounded-lg bg-surface p-6 sm:p-8">
                <h3 class="text-sm font-semibold text-brand">Our mission</h3>
                <p class="text-lg leading-normal text-ink">To empower the farming community of Lian, Batangas through accurate climate information, clear analysis, and practical recommendations.</p>
                <hr class="border-line">
                <p class="text-sm text-muted">Information drawn from</p>
                <ul class="flex flex-wrap gap-2">
                    @foreach ($sources as $source)
                        <li class="lp-tag bg-white">{{ $source }}</li>
                    @endforeach
                </ul>
            </div>
        </div>

        <ul data-reveal-group class="mt-12 grid gap-6 md:grid-cols-3 lg:mt-16">
            @foreach ($facts as $fact)
                <li data-reveal class="lp-card">
                    <span class="lp-icon-circle"><x-icon :name="$fact['icon']" class="h-5 w-5" /></span>
                    <h3 class="mt-5 text-xl font-semibold text-ink">{{ $fact['title'] }}</h3>
                    <p class="mt-2 text-base leading-relaxed text-muted">{{ $fact['text'] }}</p>
                </li>
            @endforeach
        </ul>
    </div>
</section>
