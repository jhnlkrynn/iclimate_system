<x-guest-layout>
    @php
        $locale = request()->query('lang') === 'fil' ? 'fil' : (app()->getLocale() === 'fil' ? 'fil' : 'en');
        $officeEmail = config('iclimate.office_email', 'mao.lian@batangas.gov.ph');
    @endphp
    <div class="container py-5" style="max-width: 760px;">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="h3 fw-bold mb-0">{{ __('privacy.consent_title', [], $locale) }}</h1>
            <div class="d-flex gap-2">
                <a href="{{ route('privacy.consent', ['lang' => 'en']) }}" class="btn btn-sm {{ $locale === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">EN</a>
                <a href="{{ route('privacy.consent', ['lang' => 'fil']) }}" class="btn btn-sm {{ $locale === 'fil' ? 'btn-primary' : 'btn-outline-secondary' }}">FIL</a>
            </div>
        </div>
        <p class="text-muted mb-4">{{ __('privacy.consent_intro', [], $locale) }}</p>

        <div class="card shadow-sm mb-4">
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

        <form method="POST" action="{{ route('privacy.consent.store') }}" class="card shadow-sm">
            @csrf
            <div class="card-body p-4">
                <div class="form-check mb-3">
                    <input class="form-check-input @error('privacy_agreed') is-invalid @enderror" type="checkbox" name="privacy_agreed" id="privacyAgreed" value="1" required>
                    <label class="form-check-label" for="privacyAgreed">
                        {{ __('privacy.agree_label', [], $locale) }}
                    </label>
                    @error('privacy_agreed')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <button type="submit" class="btn btn-primary">{{ __('privacy.consent_button', [], $locale) }}</button>
                </div>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-3 text-end">
            @csrf
            <button type="submit" class="btn btn-link btn-sm text-muted">{{ __('privacy.consent_logout', [], $locale) }}</button>
        </form>
    </div>
</x-guest-layout>
