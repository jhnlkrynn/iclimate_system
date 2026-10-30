{{-- Common questions (white). One answer open at a time; resources/js/landing.js runs the accordion. --}}
@php
    $questions = [
        ['q' => 'Who can use iClimate?', 'a' => 'Farmers in Lian, MAO Staff, and IT Personnel. Each account opens its own dashboard: the Farmer Dashboard, the MAO Staff Dashboard or the IT Personnel Dashboard.'],
        ['q' => 'Where does the weather data come from?', 'a' => 'PAGASA advisories, Open-Meteo forecasts, stored climate records for Lian, and the MAO’s rice production records.'],
        ['q' => 'How do I get an account?', 'a' => 'Use Create account at the top of this page. If you need help, contact the MAO during office hours.'],
    ];
@endphp
<section id="faq" data-section aria-labelledby="faq-title" class="lp-section">
    <div class="lp-wrap flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-16">
        <div class="flex w-full flex-col gap-8 lg:w-[400px] lg:shrink-0">
            <x-landing-heading id="faq-title" eyebrow="Questions" lead="Can’t find what you need? Write to the Municipal Agriculture Office below.">
                Common<br class="max-sm:hidden"> questions
            </x-landing-heading>
            {{-- Figma shows "Prefer to talk? Call ..."; there is no confirmed office phone number yet, so this points to email and office hours instead. --}}
            <div data-reveal class="flex items-start gap-3 rounded-lg bg-surface p-4">
                <x-icon name="mail" class="mt-0.5 h-[18px] w-[18px] text-brand" />
                <div class="text-sm leading-[1.4]">
                    <p class="font-semibold text-ink">Prefer to talk?</p>
                    <p class="mt-0.5 text-muted">Email <a href="mailto:{{ $officeEmail }}" class="lp-link">{{ $officeEmail }}</a> or visit the office, Mon to Fri, 8 AM to 5 PM.</p>
                </div>
            </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-4" data-accordion data-reveal-group>
            @foreach ($questions as $item)
                <div class="rounded-lg border border-line bg-white" data-accordion-item data-reveal>
                    <h3>
                        <button type="button" id="faq-q-{{ $loop->index }}" aria-controls="faq-a-{{ $loop->index }}" aria-expanded="{{ $loop->first ? 'true' : 'false' }}"
                                class="group flex w-full items-center justify-between gap-4 rounded-lg p-4 text-left text-base font-semibold leading-[1.4] text-ink hover:bg-surface" data-accordion-trigger>
                            {{ $item['q'] }}
                            <x-icon name="chevron-down" class="h-5 w-5 transition-transform group-aria-expanded:rotate-180" />
                        </button>
                    </h3>
                    <div id="faq-a-{{ $loop->index }}" role="region" aria-labelledby="faq-q-{{ $loop->index }}" class="px-4 pb-4 text-base leading-[1.4] text-ink" @unless ($loop->first) hidden @endunless data-accordion-panel>
                        {{ $item['a'] }}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
