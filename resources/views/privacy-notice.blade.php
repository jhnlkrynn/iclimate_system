<x-guest-layout>
    @php
        $locale = request()->query('lang') === 'fil' ? 'fil' : (app()->getLocale() === 'fil' ? 'fil' : 'en');
        $officeEmail = config('iclimate.office_email', 'mao.lian@batangas.gov.ph');
    @endphp
    <div class="container py-5" style="max-width: 760px;">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 fw-bold mb-0">{{ __('privacy.title', [], $locale) }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('privacy.notice', ['lang' => 'en']) }}" class="btn btn-sm {{ $locale === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</a>
                <a href="{{ route('privacy.notice', ['lang' => 'fil']) }}" class="btn btn-sm {{ $locale === 'fil' ? 'btn-primary' : 'btn-outline-secondary' }}">FIL</a>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body p-4 d-grid gap-3">
                <section>
                    <h2 class="h6 fw-bold">{{ __('privacy.sections.collected_title', [], $locale) }}</h2>
                    <p class="mb-0 text-muted">{{ __('privacy.sections.collected_body', [], $locale) }}</p>
                </section>
                <section>
                    <h2 class="h6 fw-bold">{{ __('privacy.sections.purpose_title', [], $locale) }}</h2>
                    <p class="mb-0 text-muted">{{ __('privacy.sections.purpose_body', [], $locale) }}</p>
                </section>
                <section>
                    <h2 class="h6 fw-bold">{{ __('privacy.sections.access_title', [], $locale) }}</h2>
                    <p class="mb-0 text-muted">{{ __('privacy.sections.access_body', [], $locale) }}</p>
                </section>
                <section>
                    <h2 class="h6 fw-bold">{{ __('privacy.sections.retention_title', [], $locale) }}</h2>
                    <p class="mb-0 text-muted">{{ __('privacy.sections.retention_body', [], $locale) }}</p>
                </section>
                <section>
                    <h2 class="h6 fw-bold">{{ __('privacy.sections.contact_title', [], $locale) }}</h2>
                    <p class="mb-0 text-muted">{{ __('privacy.sections.contact_body', ['email' => $officeEmail], $locale) }}</p>
                </section>
            </div>
        </div>
    </div>
</x-guest-layout>
