{{-- The farmer sign-up form, shared by /register (auth/register.blade.php) and the landing page card (landing/hero.blade.php).
     Posts to route('register'); RegisteredUserController validates it. Styles: register-form-styles; behaviour: register-form-script.
     Optional: $formErrors (default $errors) and $useOld (default true), so another form on the same page keeps its own errors and
     old input; $captchaLazy (default false) to load the security picture only when the form is shown.
     $farmerOrganizations and $manualBarangays come from RegisteredUserController::create(); the landing page builds the same lists. --}}
@php
    $formErrors = $formErrors ?? $errors;
    $useOld = $useOld ?? true;
    $captchaLazy = $captchaLazy ?? false;
    $old = fn (string $key, $default = null) => $useOld ? old($key, $default) : $default;
@endphp
<div class="reg-head">
  <p class="reg-sub">Register as a rice farmer in Lian, Batangas.</p>
  <div id="formMessage" role="alert" @unless($formErrors->any()) hidden @endunless>Please check the highlighted fields below and try again.</div>
</div>

<form method="POST" action="{{ route('register') }}" class="register-form" data-loader-text="Creating account...">
  @csrf
  {{-- Tells the landing page which card to reopen when the sign-up comes back with errors. --}}
  <input type="hidden" name="_auth_form" value="register">
  <input type="hidden" id="name" name="name" value="{{ $old('name') }}">

  <section class="form-section" aria-labelledby="sectionPersonal">
    <h2 class="sr-only" id="sectionPersonal">Personal information</h2>

    <div class="form-row-2col" data-reg-row="name">
      <div class="form-group">
        <label for="firstName">First name</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
          <input type="text" id="firstName" name="first_name" value="{{ $old('first_name') }}" class="form-input has-icon @if($formErrors->has('name')) is-invalid @endif" placeholder="Juan" autocomplete="given-name" aria-describedby="firstName-error" @if($formErrors->has('name')) aria-invalid="true" @endif required/>
        </div>
        <p class="field-error" id="firstName-error" @unless($formErrors->has('name')) hidden @endunless>{{ $formErrors->first('name') }}</p>
      </div>
      <div class="form-group">
        <label for="lastName">Last name</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
          <input type="text" id="lastName" name="last_name" value="{{ $old('last_name') }}" class="form-input has-icon @if($formErrors->has('name')) is-invalid @endif" placeholder="Dela Cruz" autocomplete="family-name" aria-describedby="lastName-error" required/>
        </div>
        <p class="field-error" id="lastName-error" hidden></p>
      </div>
    </div>

    <div class="form-group">
      <label for="email">Email address</label>
      <div class="input-wrap">
        <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1.5" y="3" width="13" height="10" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 5l6 4.2L14 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
        <input type="email" id="email" name="email" value="{{ $old('email') }}" class="form-input has-icon @if($formErrors->has('email')) is-invalid @endif" placeholder="your.email@example.com" autocomplete="username" aria-describedby="email-error" @if($formErrors->has('email')) aria-invalid="true" @endif required/>
      </div>
      <p class="field-error" id="email-error" @unless($formErrors->has('email')) hidden @endunless>{{ $formErrors->first('email') }}</p>
    </div>
  </section>

  <section class="form-section" aria-labelledby="sectionSecurity">
    <h2 class="sr-only" id="sectionSecurity">Account security</h2>

    <div class="form-row-2col" data-reg-row="password">
      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
          <input type="password" id="password" name="password" class="form-input has-icon has-toggle @if($formErrors->has('password')) is-invalid @endif" placeholder="Create a password" autocomplete="new-password" aria-describedby="password-error passwordHelper" @if($formErrors->has('password')) aria-invalid="true" @endif required/>
          <x-password-toggle for="password" id="pwdToggle" class="pwd-toggle" />
        </div>
        <p class="field-error" id="password-error" @unless($formErrors->has('password')) hidden @endunless>{{ $formErrors->first('password') }}</p>
      </div>

      <div class="form-group">
        <label for="confirmPassword">Confirm password</label>
        <div class="input-wrap">
          <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
          <input type="password" id="confirmPassword" name="password_confirmation" class="form-input has-icon has-toggle @if($formErrors->has('password_confirmation')) is-invalid @endif" placeholder="Re-enter password" autocomplete="new-password" aria-describedby="confirmPassword-error confirmPassword-success" required/>
          <x-password-toggle for="confirmPassword" id="confirmPwdToggle" class="pwd-toggle" />
        </div>
        <p class="field-error" id="confirmPassword-error" @unless($formErrors->has('password_confirmation')) hidden @endunless>{{ $formErrors->first('password_confirmation') }}</p>
        <p class="field-success" id="confirmPassword-success" aria-live="polite" hidden>Passwords match.</p>
      </div>
    </div>

    {{-- One line: strength bar, then each requirement as a small pill that turns green when met. --}}
    <div class="pwd-helper" id="passwordHelper">
      <div class="pwd-strength-meta">
        <span class="pwd-strength-label" id="passwordStrengthLabel" aria-live="polite">Strength: <strong>&mdash;</strong></span>
        <div class="pwd-strength-track" aria-hidden="true"><div class="pwd-strength-fill" id="passwordStrengthFill"></div></div>
      </div>
      <ul class="pwd-guide" id="passwordGuide" aria-label="Password requirements">
        @foreach(['length' => ['8+ characters', 'At least 8 characters'], 'lowercase' => ['Small letter', 'Lowercase letter'], 'uppercase' => ['Capital letter', 'Uppercase letter'], 'number' => ['Number', 'Number'], 'symbol' => ['Symbol', 'Symbol']] as $rule => [$ruleShort, $ruleLabel])
          <li class="pwd-guide-item" data-password-rule="{{ $rule }}">
            <svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true"><path d="M2 5.2l2 2 4-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            <span aria-hidden="true">{{ $ruleShort }}</span><span class="sr-only">{{ $ruleLabel }}<span data-rule-state> (not yet met)</span></span>
          </li>
        @endforeach
      </ul>
    </div>

    <div class="captcha-row">
      <label for="captcha_answer">
        <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 2l5 2v4.2c0 3.1-1.9 5.8-5 7.1-3.1-1.3-5-4-5-7.1V4l5-2z" stroke="currentColor" stroke-width="1.5"/><path d="M6.8 9l1.4 1.4 3.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Security check
      </label>
      {{-- One captcha per session: on the landing page the picture loads only while this form is showing (landing.js). --}}
      @if ($captchaLazy)
        <img class="captcha-image" width="110" height="38" data-captcha-src="{{ route('captcha.image', ['context' => 'register']) }}" alt="Security code image">
      @else
        <img class="captcha-image" width="110" height="38" src="{{ route('captcha.image', ['context' => 'register', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
      @endif
      <div class="form-group captcha-field">
        <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @if($formErrors->has('captcha_answer')) is-invalid @endif" placeholder="Enter the code" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" aria-describedby="captcha_answer-error" @if($formErrors->has('captcha_answer')) aria-invalid="true" @endif required/>
        <p class="field-error" id="captcha_answer-error" @unless($formErrors->has('captcha_answer')) hidden @endunless>{{ $formErrors->first('captcha_answer') }}</p>
      </div>
    </div>
  </section>

  <section class="form-section" aria-labelledby="sectionAgreement">
    <h2 class="sr-only" id="sectionAgreement">{{ __('privacy.title') }}</h2>

    <div class="form-group">
      <p class="small text-muted mb-2" id="agreeTerms-note">{{ __('privacy.signup_short') }} <a href="{{ route('privacy.notice') }}" target="_blank" rel="noopener noreferrer">{{ __('privacy.link') }}</a>.</p>
      <div class="terms-row">
        <input type="checkbox" id="agreeTerms" name="privacy_agreed" value="1" aria-describedby="agreeTerms-note agreeTerms-error" @checked(old('privacy_agreed')) required/>
        <label for="agreeTerms">{{ __('privacy.agree_label') }}</label>
      </div>
      <p class="field-error" id="agreeTerms-error" @unless($formErrors->has('privacy_agreed')) hidden @endunless>{{ $formErrors->first('privacy_agreed') }}</p>
    </div>
  </section>

  <button class="btn-submit" id="createAccountBtn" type="submit">Create account</button>
</form>
