{{-- Footer (dark ink): what iClimate is, link columns, the MAO's card, the Data Privacy notice (closed until opened) and
     the bottom row. --}}
@php
    $footerColumns = [
        'Platform' => [
            ['href' => '#about', 'label' => 'About', 'kind' => 'link', 'key' => 'about'],
            ['href' => '#features', 'label' => 'Features', 'kind' => 'link', 'key' => 'features'],
            ['href' => '#how', 'label' => 'How it works', 'kind' => 'link', 'key' => 'how'],
            ['href' => '#roles', 'label' => 'Who it’s for', 'kind' => 'link', 'key' => 'roles'],
            ['href' => '#faq', 'label' => 'Questions', 'kind' => 'link', 'key' => 'faq'],
        ],
        'Account' => [
            ['href' => route('login'), 'label' => 'Log in', 'kind' => 'account', 'key' => 'login'],
            ['href' => auth()->guest() ? '#register' : route('register'), 'label' => 'Create account', 'kind' => 'account', 'key' => 'register'],
            ['href' => route('password.request'), 'label' => 'Forgot password', 'kind' => 'account', 'key' => 'password'],
        ],
    ];
@endphp
<footer class="bg-ink pb-8 pt-14 text-white/75 lg:pt-20">
    <div class="lp-wrap">
        <div class="grid grid-cols-2 gap-x-6 gap-y-10 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto_minmax(0,1fr)] lg:gap-12">
            <div class="col-span-2 flex flex-col items-start gap-4 lg:col-span-1">
                <a href="{{ url('/') }}" class="flex items-center gap-3 rounded-md" aria-label="iClimate home" data-footer-home>
                    <img src="{{ asset('images/mao-lian-seal.png') }}" alt="Municipal Agriculture Office logo" width="40" height="40" class="h-10 w-10 rounded-full object-contain">
                    <span class="h-8 w-px bg-white/30" aria-hidden="true"></span>
                    <img src="{{ asset('images/iclimate-logo-light.png') }}" alt="" width="50" height="40" class="h-10 w-[50px] object-contain">
                </a>
                <p class="text-base leading-relaxed">Weather, planting advice and rice yield forecasts for the farmers of Lian, Batangas. A capstone project of Batangas State University ARASOF-Nasugbu with the Municipal Agriculture Office.</p>
            </div>

            @foreach ($footerColumns as $heading => $links)
                <nav aria-labelledby="footer-{{ Str::slug($heading) }}">
                    <h2 id="footer-{{ Str::slug($heading) }}" class="text-sm font-semibold uppercase tracking-[0.12em] text-white">{{ $heading }}</h2>
                    <ul class="mt-4 flex flex-col gap-3 text-base">
                        @foreach ($links as $link)
                            <li><a href="{{ $link['href'] }}" class="rounded hover:text-white" data-footer-{{ $link['kind'] }}="{{ $link['key'] }}">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach

            <div class="col-span-2 sm:col-span-1">
                <h2 class="text-sm font-semibold uppercase tracking-[0.12em] text-white">Contact</h2>
                <ul class="mt-4 flex flex-col gap-3 text-base">
                    <li><a href="mailto:{{ $officeEmail }}" class="rounded hover:text-white" data-footer-email>{{ $officeEmail }}</a></li>
                    <li><a href="https://www.facebook.com/profile.php?id=61551743430540" target="_blank" rel="noopener noreferrer" class="rounded hover:text-white" data-footer-facebook>Facebook<span class="sr-only"> (opens in a new tab)</span></a></li>
                    <li><a href="#contact" class="rounded hover:text-white" data-footer-link="contact">Send a message</a></li>
                    <li>Mon to Fri, 8 AM to 5 PM</li>
                </ul>
            </div>

            <address class="flex items-start gap-4 self-start rounded-lg border border-white/15 p-5 not-italic col-span-2 sm:col-span-1" data-footer-mao>
                <img src="{{ asset('images/mao-lian-seal.png') }}" alt="" width="48" height="48" class="h-12 w-12 shrink-0 rounded-full object-contain">
                <div class="text-sm leading-relaxed">
                    <p class="text-base font-semibold text-white">{{ \App\Support\MaoContact::OFFICE }}</p>
                    {{-- The office's name is the heading, so the address starts at the building. --}}
                    <p class="mt-1">{{ Str::after(\App\Support\MaoContact::ADDRESS, \App\Support\MaoContact::OFFICE.', ') }}</p>
                    <a href="{{ $directionsUrl }}" target="_blank" rel="noopener noreferrer" class="mt-2 inline-flex min-h-11 items-center gap-1.5 rounded font-semibold text-white underline underline-offset-2 hover:no-underline">
                        Get directions<span class="sr-only"> (opens in a new tab)</span>
                    </a>
                </div>
            </address>
        </div>

        {{-- The wording should be confirmed by the MAO's Data Protection Officer before launch. --}}
        <details class="group mt-12 rounded-lg border border-white/15" data-privacy>
            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-4 rounded-lg px-5 py-3 font-semibold text-white [&::-webkit-details-marker]:hidden">
                <span>Data Privacy notice</span>
                <x-icon name="chevron-down" class="h-5 w-5 group-open:rotate-180" />
            </summary>
            <div class="flex flex-col gap-3 px-5 pb-5 text-sm leading-relaxed">
                <p>iClimate collects your name, contact details, barangay and farm information to give you weather, planting and harvest information and to help the Municipal Agriculture Office of Lian plan its services.</p>
                <p>Your information is handled under the Data Privacy Act of 2012 (Republic Act No. 10173). Only authorized MAO Staff and IT Personnel can see it, and it is used only for the purposes above.</p>
                <p>To see, correct or remove your information, email <a href="mailto:{{ $officeEmail }}" class="font-semibold text-white underline underline-offset-2">{{ $officeEmail }}</a> or visit the office.</p>
            </div>
        </details>

        <div class="mt-8 flex flex-col gap-3 border-t border-white/15 pt-6 text-sm sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} iClimate · Capstone Project</p>
            <a href="#top" class="inline-flex min-h-11 w-fit items-center rounded font-semibold text-white hover:underline" data-back-to-top>
                Back to top
            </a>
        </div>
    </div>
</footer>
