@php
    $locale = request()->query('lang') === 'fil' ? 'fil' : (app()->getLocale() === 'fil' ? 'fil' : 'en');
    $officeEmail = config('iclimate.office_email', \App\Support\MaoContact::EMAIL);
    $routeName = $showConsent ? 'privacy.consent' : 'privacy.notice';
    $sectionPrefix = $showConsent ? 'privacy.short_sections' : 'privacy.sections';
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

    <div class="ic-privacy-grid">
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.collected_title', [], $locale) }}</h2>
            <p>{{ __($sectionPrefix.'.collected_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.purpose_title', [], $locale) }}</h2>
            <p>{{ __($sectionPrefix.'.purpose_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.access_title', [], $locale) }}</h2>
            <p>{{ __($sectionPrefix.'.access_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section">
            <h2>{{ __('privacy.sections.retention_title', [], $locale) }}</h2>
            <p>{{ __($sectionPrefix.'.retention_body', [], $locale) }}</p>
        </section>
        <section class="ic-privacy-section ic-privacy-section--full">
            <h2>{{ __('privacy.sections.contact_title', [], $locale) }}</h2>
            @if ($showConsent)
                <p>{{ __('privacy.short_sections.contact_body', ['office' => \App\Support\MaoContact::OFFICE, 'address' => \App\Support\MaoContact::ADDRESS, 'hours' => \App\Support\MaoContact::HOURS, 'email' => $officeEmail], $locale) }}</p>
            @else
                <p>{{ __('privacy.sections.contact_body', ['email' => $officeEmail], $locale) }} ({{ \App\Support\MaoContact::ADDRESS }} · {{ \App\Support\MaoContact::HOURS }})</p>
            @endif
        </section>
    </div>

    @if ($showConsent)
        <div class="ic-privacy-full-wrap">
            <a href="{{ route('privacy.notice', ['lang' => $locale]) }}" target="_blank" rel="noopener noreferrer" class="ic-privacy-full-link">
                {{ __('privacy.read_full', [], $locale) }}
            </a>
        </div>

        <div class="ic-privacy-agree-band">
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
        <a href="{{ url('/') }}" class="ic-privacy-footer-link">{{ __('privacy.back_home', [], $locale) }}</a>
    @endif
</div>
