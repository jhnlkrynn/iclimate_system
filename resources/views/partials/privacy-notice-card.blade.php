@php
    $locale = request()->query('lang') === 'fil' ? 'fil' : (app()->getLocale() === 'fil' ? 'fil' : 'en');
    $officeEmail = config('iclimate.office_email', 'mao.lian@batangas.gov.ph');
    $routeName = $showConsent ? 'privacy.consent' : 'privacy.notice';
@endphp
<div class="ic-privacy-card">
    <div class="ic-privacy-top">
        <div>
            <p class="ic-privacy-eyebrow">{{ __('privacy.eyebrow', [], $locale) }}</p>
            <h1 class="ic-privacy-title">{{ __('privacy.consent_title', [], $locale) }}</h1>
            <p class="ic-privacy-intro">{{ __($showConsent ? 'privacy.consent_intro' : 'privacy.notice_intro', [], $locale) }}</p>
        </div>
        <div class="ic-privacy-flags" role="group" aria-label="Language">
            @foreach (['en' => 'English', 'fil' => 'Filipino'] as $langCode => $langName)
                <a href="{{ route($routeName, ['lang' => $langCode]) }}"
                   class="ic-privacy-flag-btn"
                   lang="{{ $langCode }}"
                   aria-pressed="{{ $locale === $langCode ? 'true' : 'false' }}"
                   title="{{ $langName }}"
                   aria-label="{{ $langName }}">@include('layouts.partials.lang-flag', ['code' => $langCode])</a>
            @endforeach
        </div>
    </div>

    <div class="ic-privacy-scroll" tabindex="0" aria-label="{{ __('privacy.consent_title', [], $locale) }}">
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.collected_title', [], $locale) }}</h2>
            <p>{{ __('privacy.sections.collected_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.purpose_title', [], $locale) }}</h2>
            <p>{{ __('privacy.sections.purpose_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.access_title', [], $locale) }}</h2>
            <p>{{ __('privacy.sections.access_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.retention_title', [], $locale) }}</h2>
            <p>{{ __('privacy.sections.retention_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.contact_title', [], $locale) }}</h2>
            <p>{{ __('privacy.sections.contact_body', ['email' => $officeEmail], $locale) }}</p>
        </section>
    </div>

    @if ($showConsent)
        <div class="ic-privacy-sticky">
            <form method="POST" action="{{ route('privacy.consent.store') }}" data-privacy-agree-form>
                @csrf
                <label class="ic-privacy-check" for="privacyAgreed">
                    <input type="checkbox" name="privacy_agreed" id="privacyAgreed" value="1" required data-privacy-agree-checkbox>
                    <span>{{ __('privacy.agree_label', [], $locale) }}</span>
                </label>
                @error('privacy_agreed')
                    <p class="ic-privacy-error">{{ $message }}</p>
                @enderror
                <button type="submit" class="ic-privacy-submit" data-privacy-agree-submit disabled>{{ __('privacy.consent_button', [], $locale) }}</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="ic-privacy-footer-link">{{ __('privacy.consent_logout', [], $locale) }}</button>
            </form>
        </div>
    @else
        <div class="ic-privacy-sticky">
            <a href="{{ url('/') }}" class="ic-privacy-footer-link">{{ __('privacy.back_home', [], $locale) }}</a>
        </div>
    @endif
</div>
