{{-- Numbers band (dark): real counts from the database (App\Support\LandingStats, cached until midnight). Left out when
     the database cannot be read. --}}
@php
    $stats = \App\Support\LandingStats::get();
@endphp
@if ($stats)
    @php
        $figures = [
            'farmers' => 'Registered farmers',
            'farms' => 'Farms on record',
            'barangays' => 'Barangays with farms',
            'varieties' => 'Rice varieties listed',
        ];
    @endphp
    <section id="numbers" data-section aria-labelledby="numbers-title" class="lp-section lp-band">
        <div class="lp-band-photo" style="background-image: url('{{ asset('images/rice-hero-field.jpg') }}?v={{ filemtime(public_path('images/rice-hero-field.jpg')) }}')" aria-hidden="true"></div>
        <div class="lp-wrap grid gap-10 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] lg:items-center lg:gap-16">
            <x-landing-heading id="numbers-title" eyebrow="iClimate in Lian" lead="Counted from iClimate’s own records and updated every day.">
                The farms we<br class="max-sm:hidden"> work with
            </x-landing-heading>

            <dl data-reveal-group class="grid grid-cols-2 gap-x-6 gap-y-10 sm:grid-cols-4 lg:gap-x-8">
                @foreach ($figures as $key => $label)
                    <div data-reveal class="flex flex-col-reverse gap-2 border-l border-white/25 pl-4" data-stat="{{ $key }}">
                        <dt class="text-sm leading-snug text-white/80">{{ $label }}</dt>
                        <dd class="text-[2rem] font-bold leading-none tracking-[-0.02em] sm:text-[2.5rem]">{{ number_format($stats[$key]) }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>
@endif
