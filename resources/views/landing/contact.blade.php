{{-- Contact (Figma: Contact). The form posts to contact.send (JSON); resources/js/landing.js shows its errors and reply. --}}
@php
    $contactRoles = ['Rice Farmer' => 'Farmer', 'MAO Personnel' => 'MAO Staff', 'IT Personnel' => 'IT Personnel', 'Other' => 'Other'];
@endphp
<section id="contact" data-section aria-labelledby="contact-title" class="lp-screen py-16 lg:py-20">
    <div class="lp-wrap"><div class="lp-panel">
        <h2 id="contact-title" data-reveal class="lp-h2">Get in touch</h2>

        <div class="mt-10 grid gap-6 lg:grid-cols-2">
            <div class="flex min-w-0 flex-col gap-6">
                {{-- Google Maps embed (no API key). The pin is on Lian Municipal Hall (q); the view is centred mid-Lian (ll) at zoom 11
                     so the whole municipality, Bucana to Matabungkay, fits the 260px box. The fixed height keeps the layout from shifting. --}}
                <div class="h-[260px] overflow-hidden rounded-[14px] bg-tint ring-brand ring-offset-2 [&.is-focused]:ring-2" data-office-map-box>
                    <iframe src="https://maps.google.com/maps?q=Lian%20Municipal%20Hall%2C%20Lian%2C%20Batangas&amp;ll=13.992,120.648&amp;z=11&amp;output=embed"
                            title="Map of the Municipal Agriculture Office, Lian Municipal Hall, Lian, Batangas"
                            loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen
                            class="block h-full w-full border-0" data-office-map></iframe>
                </div>

                <dl data-reveal-group class="flex flex-col gap-6 text-base leading-[1.4]">
                    <div data-reveal class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                        <dt class="flex w-[140px] shrink-0 items-center gap-3 text-muted"><x-icon name="map-pin" class="h-[18px] w-[18px] text-brand" />Address</dt>
                        <dd class="text-ink">
                            {{ \App\Support\MaoContact::ADDRESS }}
                            <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="lp-link mt-1 block w-fit" data-directions>Get directions<span class="sr-only"> (opens in a new tab)</span></a>
                        </dd>
                    </div>
                    <div data-reveal class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                        <dt class="flex w-[140px] shrink-0 items-center gap-3 text-muted"><x-icon name="mail" class="h-[18px] w-[18px] text-brand" />Email</dt>
                        <dd><a href="mailto:{{ $officeEmail }}" class="lp-link" data-contact-email>{{ $officeEmail }}</a></dd>
                    </div>
                    {{-- Phone row (Figma) left out until the office confirms a number; add it back as a tel: link. --}}
                    <div data-reveal class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                        <dt class="flex w-[140px] shrink-0 items-center gap-3 text-muted"><x-icon name="clock" class="h-[18px] w-[18px] text-brand" />Office hours</dt>
                        <dd class="text-ink">{{ \App\Support\MaoContact::HOURS }}</dd>
                    </div>
                </dl>
            </div>

            <form id="contactForm" method="POST" action="{{ route('contact.send') }}" novalidate class="flex min-w-0 flex-col gap-[18px] rounded-[14px] border border-line p-6 sm:p-8">
                @csrf
                <div>
                    <h3 class="text-xl font-semibold leading-[1.2] tracking-[-0.02em] text-ink">Send a message</h3>
                    <p class="mt-1 text-sm leading-[1.4] text-muted">We usually reply within one working day.</p>
                </div>

                <div>
                    <label for="contact-name" class="lp-label">Full name</label>
                    <input type="text" id="contact-name" name="name" maxlength="120" autocomplete="name" required placeholder="Juan Dela Cruz" class="lp-input" aria-describedby="contact-name-error">
                    <p id="contact-name-error" class="lp-error" data-error-for="name" hidden></p>
                </div>

                <div class="grid gap-[18px] sm:grid-cols-2">
                    <div>
                        <label for="contact-email" class="lp-label">Email</label>
                        <input type="email" id="contact-email" name="email" maxlength="255" autocomplete="email" required placeholder="juan@gmail.com" class="lp-input" aria-describedby="contact-email-error">
                        <p id="contact-email-error" class="lp-error" data-error-for="email" hidden></p>
                    </div>
                    <div>
                        <label for="contact-role" class="lp-label">I am a</label>
                        <select id="contact-role" name="role" class="lp-input" aria-describedby="contact-role-error">
                            <option value="">Choose one (optional)</option>
                            @foreach ($contactRoles as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <p id="contact-role-error" class="lp-error" data-error-for="role" hidden></p>
                    </div>
                </div>

                <div>
                    <label for="contact-message" class="lp-label">Message</label>
                    <textarea id="contact-message" name="message" rows="3" maxlength="2000" required placeholder="How can we help?" class="lp-input leading-[1.4]" aria-describedby="contact-message-error"></textarea>
                    <p id="contact-message-error" class="lp-error" data-error-for="message" hidden></p>
                </div>

                {{-- Left empty by people; automated form fillers complete it and are refused. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="absolute -left-[9999px] h-px w-px opacity-0">

                <p id="contactStatus" role="status" aria-live="polite" class="rounded-lg px-3 py-2 text-sm" hidden></p>
                <button type="submit" id="contactSubmit" class="lp-btn-primary w-full disabled:opacity-60">Send message</button>
            </form>
        </div>
    </div></div>
</section>
