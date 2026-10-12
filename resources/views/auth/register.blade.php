@php($errors = $errors ?? new \Illuminate\Support\ViewErrorBag)
<!DOCTYPE html>
<html lang="{{ str_replace('_' , '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Create account | {{ config('app.name', 'iClimate') }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    :root {
      --reg-green-dark: #123F32;
      --reg-green: #1F684F;
      --reg-mint: #7FD6B5;
      --reg-green-bg: #F6F8F7;
      --reg-border: #E3EAE6;
      --reg-input-border: #D5DFD9;
      --reg-text: #1E2B25;
      --reg-text-2: #65736C;
      --reg-error: #B42318;
      --reg-success: #16794A;
      --reg-sans: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    }
    html, body { margin: 0; min-height: 100%; }
    body { font-family: var(--reg-sans); font-size: 15px; line-height: 1.5; color: var(--reg-text); -webkit-font-smoothing: antialiased; }
    a { color: inherit; }
    button, input, select { font: inherit; }
    h1, h2, h3, p { margin: 0; }
    .sr-only { position: absolute; width: 1px; height: 1px; margin: -1px; padding: 0; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0; }

    body.register-page { min-height: 100vh; background: #fff; }
@include('auth.partials.split-styles')

    /* -- PHOTO SIDE: what a farmer account gives you ------ */
    .reg-notes-title { font-size: 17px; font-weight: 600; letter-spacing: -0.01em; }
    .reg-notes-sub { font-size: 14px; color: rgba(255,255,255,.8); margin-top: 2px; }
    .perk-list { list-style: none; margin: 18px 0 0; padding: 18px 0 0; border-top: 1px solid rgba(255,255,255,.22); display: flex; flex-direction: column; gap: 10px; }
    .perk-item { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.45; color: #fff; }
    .perk-item svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 2px; color: var(--reg-mint); }
    .reg-notes-staff { margin-top: 18px; padding-top: 16px; border-top: 1px solid rgba(255,255,255,.22); font-size: 13px; line-height: 1.5; color: rgba(255,255,255,.82); }

    /* -- FORM SIDE ----------------------------------------- */
    .form-card { width: 100%; max-width: 440px; }
    .login-form-header { text-align: center; margin-bottom: 8px; }
    .card-logo { display: flex; justify-content: center; margin-bottom: 18px; }
    .card-logo img { height: 48px; width: auto; display: block; }
    .login-form-header h1 { font-size: 30px; font-weight: 600; letter-spacing: -0.03em; line-height: 1.15; margin-bottom: 6px; }
    .login-form-header p { font-size: 15px; color: var(--reg-text-2); }

    #formMessage { margin-top: 16px; padding: 10px 14px; font-size: 14px; color: var(--reg-error); background: #FEF3F2; border: 1px solid #F4C7C3; border-radius: 12px; }

    .form-section { margin-top: 24px; }
    .form-section-title { font-size: 15px; font-weight: 600; letter-spacing: -0.01em; color: var(--reg-text); margin-bottom: 12px; }
    .form-row-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .form-group { min-width: 0; }
    .form-group + .form-group, .form-row-2col + .form-group { margin-top: 14px; }
    .form-row-2col + .pwd-helper { margin-top: 12px; }
    .form-row-2col > .form-group + .form-group { margin-top: 0; }
    .form-group label { display: block; font-size: 13px; font-weight: 500; color: var(--reg-text-2); margin-bottom: 6px; }

    .input-wrap { position: relative; }
    .form-input {
      width: 100%; min-width: 0; height: 48px; background: var(--reg-green-bg); border: 1px solid transparent; border-radius: 12px;
      padding: 0 14px; font-size: 15px; color: var(--reg-text); outline: none;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .form-input.has-icon { padding-left: 42px; }
    .form-input.has-toggle { padding-right: 46px; }
    .form-input::placeholder { color: #98A39D; }
    .form-input:hover:not(:focus):not(:disabled):not(.is-invalid) { background: #F1F4F2; }
    .form-input:focus { background: #fff; border-color: var(--reg-green); box-shadow: 0 0 0 4px rgba(31,104,79,.12); }
    .form-input:disabled { color: var(--reg-text-2); }
    .form-input.is-invalid { border-color: var(--reg-error); background: #fff; }
    .form-input.is-invalid:focus { box-shadow: 0 0 0 4px rgba(180,35,24,.1); }
    select.form-input {
      appearance: none; -webkit-appearance: none; padding-right: 38px; cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%2365736C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 12px center;
    }
    .input-icon { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #7C8B83; }
    .pwd-toggle {
      position: absolute; right: 7px; top: 50%; transform: translateY(-50%); width: 34px; height: 34px; padding: 0;
      display: flex; align-items: center; justify-content: center;
      color: var(--reg-text-2); background: none; border: none; border-radius: 8px; cursor: pointer;
    }
    .pwd-toggle:hover { color: var(--reg-green); }
    .pwd-toggle-slash[hidden] { display: none; }
    .pwd-toggle:focus-visible, .form-card a:focus-visible, .terms-row input:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 2px; }

    .field-hint { font-size: 13px; color: var(--reg-text-2); margin-top: 6px; }
    .field-error, .field-success { display: flex; align-items: flex-start; gap: 6px; font-size: 13px; line-height: 1.4; margin-top: 6px; }
    .field-error[hidden], .field-success[hidden] { display: none; }
    .field-error { color: var(--reg-error); }
    .field-success { color: var(--reg-success); }
    .field-error::before, .field-success::before { content: ''; width: 14px; height: 14px; flex-shrink: 0; margin-top: 2px; background-repeat: no-repeat; background-position: center; }
    .field-error::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Ccircle cx='7' cy='7' r='6' stroke='%23B42318' stroke-width='1.4'/%3E%3Cpath d='M7 4v3.5' stroke='%23B42318' stroke-width='1.4' stroke-linecap='round'/%3E%3Ccircle cx='7' cy='9.8' r='.8' fill='%23B42318'/%3E%3C/svg%3E"); }
    .field-success::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Ccircle cx='7' cy='7' r='6' stroke='%2316794A' stroke-width='1.4'/%3E%3Cpath d='M4.4 7.2l1.8 1.8 3.4-3.8' stroke='%2316794A' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); }

    /* -- PASSWORD HELPER ----------------------------------- */
    .pwd-helper { background: var(--reg-green-bg); border-radius: 12px; padding: 14px 16px; }
    .pwd-strength-meta { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
    .pwd-strength-label { font-size: 13px; color: var(--reg-text-2); white-space: nowrap; }
    .pwd-strength-label strong { font-weight: 600; color: var(--reg-text); }
    .pwd-strength-label.is-weak strong { color: var(--reg-error); }
    .pwd-strength-label.is-fair strong { color: #8A5A00; }
    .pwd-strength-label.is-good strong { color: var(--reg-green); }
    .pwd-strength-label.is-strong strong { color: var(--reg-success); }
    .pwd-strength-track { flex: 1; height: 4px; border-radius: 2px; background: var(--reg-border); overflow: hidden; }
    .pwd-strength-fill { height: 100%; width: 0; border-radius: 2px; background: var(--reg-border); transition: width .2s ease, background-color .2s ease; }
    .pwd-strength-fill.is-weak { background: var(--reg-error); }
    .pwd-strength-fill.is-fair { background: #C98A1B; }
    .pwd-strength-fill.is-good { background: var(--reg-green); }
    .pwd-strength-fill.is-strong { background: var(--reg-success); }
    .pwd-guide { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 6px 16px; }
    .pwd-guide-item { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--reg-text-2); }
    .pwd-guide-dot { width: 16px; height: 16px; border-radius: 50%; display: grid; place-items: center; flex-shrink: 0; border: 1.5px solid #A9B8B0; background: #fff; color: transparent; }
    .pwd-guide-item.is-met { color: var(--reg-success); font-weight: 500; }
    .pwd-guide-item.is-met .pwd-guide-dot { background: var(--reg-success); border-color: var(--reg-success); color: #fff; }

    /* -- SECURITY CHECK ------------------------------------ */
    .captcha-card {
      margin-top: 12px; background: var(--reg-green-bg); border-radius: 12px; padding: 16px;
      display: grid; grid-template-columns: auto minmax(0, 1fr); align-items: start; gap: 10px 12px;
    }
    .captcha-card label { grid-column: 1 / -1; display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 500; color: var(--reg-text-2); }
    .captcha-card label svg { color: var(--reg-green); }
    .captcha-image { display: block; height: 48px; width: auto; max-width: 100%; border: 1px solid var(--reg-input-border); border-radius: 10px; background: #fff; }
    .captcha-field { min-width: 0; }
    .captcha-field .form-input { background: #fff; text-align: center; letter-spacing: .12em; }
    .captcha-field .form-input::placeholder { letter-spacing: normal; }

    /* -- AGREEMENT + SUBMIT -------------------------------- */
    .terms-row { display: flex; align-items: flex-start; gap: 10px; }
    .terms-row input { width: 18px; height: 18px; margin: 1px 0 0; flex-shrink: 0; accent-color: var(--reg-green); cursor: pointer; }
    .terms-row label { font-size: 14px; line-height: 1.45; color: var(--reg-text); cursor: pointer; }
    .terms-row a, .login-register a { color: var(--reg-green); font-weight: 500; text-decoration: none; border-radius: 3px; }
    .terms-row a:hover, .login-register a:hover { text-decoration: underline; text-underline-offset: 3px; }
    .terms-note { font-size: 13px; color: var(--reg-text-2); margin: 6px 0 0 28px; }
    #agreeTerms-error { margin-left: 28px; }

    .btn-submit {
      width: 100%; height: 50px; margin-top: 24px; display: flex; align-items: center; justify-content: center; gap: 10px;
      background: var(--reg-green-dark); color: #fff; border: none; border-radius: 980px;
      font-size: 16px; font-weight: 500; cursor: pointer; transition: background-color .2s ease, transform .2s ease;
    }
    .btn-submit:hover { background: var(--reg-green); }
    .btn-submit:active { transform: scale(.98); }
    .btn-submit:focus-visible { outline: 3px solid rgba(31,104,79,.45); outline-offset: 2px; }
    .btn-submit:disabled { opacity: .8; cursor: wait; }
    .btn-spinner { width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(255,255,255,.4); border-top-color: #fff; animation: regSpin .8s linear infinite; }
    @keyframes regSpin { to { transform: rotate(360deg); } }

    .login-register { text-align: center; font-size: 14px; color: var(--reg-text-2); margin-top: 20px; }

    @media (max-width: 560px) {
      .form-row-2col { grid-template-columns: minmax(0, 1fr); }
      .captcha-card { grid-template-columns: minmax(0, 1fr); }
      .pwd-strength-meta { flex-direction: column; align-items: stretch; gap: 6px; }
      .pwd-strength-track { flex: none; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; }
      .btn-spinner { animation-duration: 2s; }
    }
  </style>
    @include('partials.global-loader')
</head>
<body class="register-page">
  <div class="login-split">
    <aside class="login-visual">
      <a href="{{ url('/') }}" class="back-link-top">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 8H3M7 12L3 8l4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
        Back to home
      </a>
      <p class="login-visual-copy">Join the iClimate community.</p>
      <section class="glass-card" aria-labelledby="regNotesTitle">
        <h2 class="reg-notes-title" id="regNotesTitle">Rice farmer account</h2>
        <p class="reg-notes-sub">Free for farmers in Lian, Batangas.</p>
        <ul class="perk-list">
          <li class="perk-item"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Climate records and advisories</li>
          <li class="perk-item"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Seasonal planting recommendations</li>
          <li class="perk-item"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Rice production information</li>
          <li class="perk-item"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8.5l3.2 3.2L13 4.5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>Community updates and notifications</li>
        </ul>
        <p class="reg-notes-staff">MAO staff and IT accounts are set up by the system administrators. Contact the Municipal Agriculture Office if you need staff access.</p>
      </section>
    </aside>

    <main class="login-panel">
      @include('auth.partials.toggle')
      <div class="form-card">
        <div class="login-form-header">
          <div class="card-logo"><img src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate"></div>
          <h1>Create account</h1>
          <p>Register as a rice farmer in Lian, Batangas.</p>
        </div>

        <div id="formMessage" role="alert" @unless($errors->any()) hidden @endunless>Please check the highlighted fields below and try again.</div>

        <form method="POST" action="{{ route('register') }}" class="register-form" data-loader-text="Creating account...">
          @csrf
          <input type="hidden" id="name" name="name" value="{{ old('name') }}">

          <section class="form-section" aria-labelledby="sectionPersonal">
            <h3 class="form-section-title" id="sectionPersonal">Personal information</h3>

            <div class="form-row-2col">
              <div class="form-group">
                <label for="firstName">First name</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                  <input type="text" id="firstName" name="first_name" value="{{ old('first_name') }}" class="form-input has-icon @error('name') is-invalid @enderror" placeholder="Juan" autocomplete="given-name" aria-describedby="firstName-error" @error('name') aria-invalid="true" @enderror required/>
                </div>
                <p class="field-error" id="firstName-error" @unless($errors->has('name')) hidden @endunless>{{ $errors->first('name') }}</p>
              </div>
              <div class="form-group">
                <label for="lastName">Last name</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><circle cx="8" cy="5.2" r="2.6" stroke="currentColor" stroke-width="1.4"/><path d="M2.5 13.5c.6-3 2.5-4.6 5.5-4.6s4.9 1.6 5.5 4.6" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                  <input type="text" id="lastName" name="last_name" value="{{ old('last_name') }}" class="form-input has-icon @error('name') is-invalid @enderror" placeholder="Dela Cruz" autocomplete="family-name" aria-describedby="lastName-error" required/>
                </div>
                <p class="field-error" id="lastName-error" hidden></p>
              </div>
            </div>

            <div class="form-group">
              <label for="email">Email address</label>
              <div class="input-wrap">
                <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="1.5" y="3" width="13" height="10" rx="2" stroke="currentColor" stroke-width="1.4"/><path d="M2 5l6 4.2L14 5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <input type="email" id="email" name="email" value="{{ old('email') }}" class="form-input has-icon @error('email') is-invalid @enderror" placeholder="your.email@example.com" autocomplete="username" aria-describedby="email-error" @error('email') aria-invalid="true" @enderror required/>
              </div>
              <p class="field-error" id="email-error" @unless($errors->has('email')) hidden @endunless>{{ $errors->first('email') }}</p>
            </div>
          </section>

          <section class="form-section" aria-labelledby="sectionFarm">
            <h3 class="form-section-title" id="sectionFarm">Farm information</h3>

            <div class="form-row-2col">
              <div class="form-group">
                <label for="farmer_organization_id">Farmer's organization</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 13V6l6-3 6 3v7" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M5 13V8h6v5M1.5 13.2h13" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                  <select id="farmer_organization_id" name="farmer_organization_id" class="form-input has-icon @error('farmer_organization_id') is-invalid @enderror" aria-describedby="farmer_organization_id-error">
                    <option value="">None</option>
                    @foreach($farmerOrganizations as $organization)
                      <option
                        value="{{ $organization->id }}"
                        data-barangay="{{ $organization->barangay }}"
                        @selected((string) old('farmer_organization_id') === (string) $organization->id)
                      >
                        {{ $organization->name }}
                      </option>
                    @endforeach
                  </select>
                </div>
                <p class="field-error" id="farmer_organization_id-error" @unless($errors->has('farmer_organization_id')) hidden @endunless>{{ $errors->first('farmer_organization_id') }}</p>
              </div>

              <div class="form-group" id="assignedBarangayGroup" style="display:none;">
                <label for="assignedBarangay">Farm location / barangay</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
                  <input type="text" id="assignedBarangay" class="form-input has-icon" value="" aria-describedby="assignedBarangay-hint" disabled>
                </div>
                <p class="field-hint" id="assignedBarangay-hint">Set by your selected organization.</p>
              </div>

              <div class="form-group" id="manualBarangayGroup">
                <label for="barangay">Farm location / barangay</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M8 14s5-3.8 5-8A5 5 0 0 0 3 6c0 4.2 5 8 5 8Z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="6" r="1.7" stroke="currentColor" stroke-width="1.4"/></svg>
                  <select id="barangay" name="barangay" class="form-input has-icon @error('barangay') is-invalid @enderror" aria-describedby="barangay-error" @error('barangay') aria-invalid="true" @enderror>
                    <option value="">Select your barangay</option>
                    @foreach($manualBarangays as $barangay)
                      <option value="{{ $barangay }}" @selected(old('barangay') === $barangay)>{{ $barangay }}</option>
                    @endforeach
                  </select>
                </div>
                <p class="field-error" id="barangay-error" @unless($errors->has('barangay')) hidden @endunless>{{ $errors->first('barangay') }}</p>
              </div>
            </div>

            {{-- Panel revision 1.1: a farmer can belong to more than one organization. The one chosen above is the main
                 one (it sets the farm location); tick any others here. --}}
            @if ($farmerOrganizations->count() > 1)
              <fieldset class="form-group" data-additional-organizations>
                <legend class="form-legend">Other organizations you belong to <span class="field-optional">(optional)</span></legend>
                <p class="field-hint">Tick every other farmer organization you are a member of.</p>
                <div class="check-grid">
                  @foreach($farmerOrganizations as $organization)
                    <label class="check-tile" data-org-tile="{{ $organization->id }}">
                      <input type="checkbox" name="additional_organization_ids[]" value="{{ $organization->id }}" @checked(in_array((string) $organization->id, array_map('strval', (array) old('additional_organization_ids', [])), true))>
                      <span>{{ $organization->name }}<small>{{ $organization->barangay }}</small></span>
                    </label>
                  @endforeach
                </div>
                <p class="field-error" @unless($errors->has('additional_organization_ids') || $errors->has('additional_organization_ids.*')) hidden @endunless>{{ $errors->first('additional_organization_ids') ?: $errors->first('additional_organization_ids.*') }}</p>
              </fieldset>
            @endif
          </section>

          <section class="form-section" aria-labelledby="sectionSecurity">
            <h3 class="form-section-title" id="sectionSecurity">Account security</h3>

            <div class="form-row-2col">
              <div class="form-group">
                <label for="password">Password</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                  <input type="password" id="password" name="password" class="form-input has-icon has-toggle @error('password') is-invalid @enderror" placeholder="Create a password" autocomplete="new-password" aria-describedby="password-error passwordHelper" @error('password') aria-invalid="true" @enderror required/>
                  <button class="pwd-toggle" id="pwdToggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/><path class="pwd-toggle-slash" d="M2.5 2.5l11 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" hidden/></svg>
                  </button>
                </div>
                <p class="field-error" id="password-error" @unless($errors->has('password')) hidden @endunless>{{ $errors->first('password') }}</p>
              </div>

              <div class="form-group">
                <label for="confirmPassword">Confirm password</label>
                <div class="input-wrap">
                  <svg class="input-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><rect x="3" y="7" width="10" height="7" rx="1.5" stroke="currentColor" stroke-width="1.4"/><path d="M5 7V5a3 3 0 016 0v2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                  <input type="password" id="confirmPassword" name="password_confirmation" class="form-input has-icon has-toggle @error('password_confirmation') is-invalid @enderror" placeholder="Re-enter your password" autocomplete="new-password" aria-describedby="confirmPassword-error confirmPassword-success" required/>
                  <button class="pwd-toggle" id="confirmPwdToggle" type="button" aria-label="Show password" aria-pressed="false">
                    <svg width="18" height="18" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M1 8C2.5 4.5 5 3 8 3s5.5 1.5 7 5c-1.5 3.5-4 5-7 5S2.5 11.5 1 8z" stroke="currentColor" stroke-width="1.4"/><circle cx="8" cy="8" r="2" stroke="currentColor" stroke-width="1.4"/><path class="pwd-toggle-slash" d="M2.5 2.5l11 11" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" hidden/></svg>
                  </button>
                </div>
                <p class="field-error" id="confirmPassword-error" @unless($errors->has('password_confirmation')) hidden @endunless>{{ $errors->first('password_confirmation') }}</p>
                <p class="field-success" id="confirmPassword-success" aria-live="polite" hidden>Passwords match.</p>
              </div>
            </div>

            <div class="pwd-helper" id="passwordHelper">
              <div class="pwd-strength-meta">
                <span class="pwd-strength-label" id="passwordStrengthLabel" aria-live="polite">Password strength: <strong>&mdash;</strong></span>
                <div class="pwd-strength-track" aria-hidden="true"><div class="pwd-strength-fill" id="passwordStrengthFill"></div></div>
              </div>
              <ul class="pwd-guide" id="passwordGuide" aria-label="Password requirements">
                @foreach(['length' => 'At least 8 characters', 'lowercase' => 'Lowercase letter', 'uppercase' => 'Uppercase letter', 'number' => 'Number', 'symbol' => 'Symbol'] as $rule => $ruleLabel)
                  <li class="pwd-guide-item" data-password-rule="{{ $rule }}">
                    <span class="pwd-guide-dot" aria-hidden="true"><svg width="10" height="10" viewBox="0 0 10 10" fill="none"><path d="M2 5.2l2 2 4-4.4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg></span>
                    <span>{{ $ruleLabel }}<span class="sr-only" data-rule-state> (not yet met)</span></span>
                  </li>
                @endforeach
              </ul>
            </div>

            <div class="captcha-card">
              <label for="captcha_answer">
                <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 2l5 2v4.2c0 3.1-1.9 5.8-5 7.1-3.1-1.3-5-4-5-7.1V4l5-2z" stroke="currentColor" stroke-width="1.5"/><path d="M6.8 9l1.4 1.4 3.2-3.4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                Security check
              </label>
              <img class="captcha-image" width="190" height="64" src="{{ route('captcha.image', ['context' => 'register', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
              <div class="captcha-field">
                <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @error('captcha_answer') is-invalid @enderror" placeholder="Enter the code shown above" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" aria-describedby="captcha_answer-error" @error('captcha_answer') aria-invalid="true" @enderror required/>
                <p class="field-error" id="captcha_answer-error" @unless($errors->has('captcha_answer')) hidden @endunless>{{ $errors->first('captcha_answer') }}</p>
              </div>
            </div>
          </section>

          <section class="form-section" aria-labelledby="sectionAgreement">
            <h3 class="form-section-title" id="sectionAgreement">Agreement</h3>

            <div class="terms-row">
              <input type="checkbox" id="agreeTerms" aria-describedby="agreeTerms-error" required/>
              <label for="agreeTerms">I agree to the <a href="#">Terms of use</a> and <a href="#">Privacy policy</a>.</label>
            </div>
            <p class="terms-note">Your information is used to provide climate and agricultural services through iClimate.</p>
            <p class="field-error" id="agreeTerms-error" hidden></p>
          </section>

          <button class="btn-submit" id="createAccountBtn" type="submit">Create account</button>
        </form>

        <p class="login-register">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
      </div>
      <p class="login-panel-foot">&copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</p>
    </main>
  </div>

<script>
  document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('.register-form');
    const formMessage = document.getElementById('formMessage');
    const firstName = document.getElementById('firstName');
    const lastName = document.getElementById('lastName');
    const name = document.getElementById('name');
    const email = document.getElementById('email');
    const password = document.getElementById('password');
    const confirmPassword = document.getElementById('confirmPassword');
    const confirmSuccess = document.getElementById('confirmPassword-success');
    const captchaAnswer = document.getElementById('captcha_answer');
    const agreeTerms = document.getElementById('agreeTerms');
    const submit = document.getElementById('createAccountBtn');
    const organization = document.getElementById('farmer_organization_id');
    const assignedBarangayGroup = document.getElementById('assignedBarangayGroup');
    const assignedBarangay = document.getElementById('assignedBarangay');
    const manualBarangayGroup = document.getElementById('manualBarangayGroup');
    const manualBarangay = document.getElementById('barangay');
    const strengthLabel = document.getElementById('passwordStrengthLabel');
    const strengthFill = document.getElementById('passwordStrengthFill');

    // Inline messages replace the browser's validation bubbles; the server still validates everything.
    form.noValidate = true;

    const syncName = () => {
      name.value = `${firstName.value.trim()} ${lastName.value.trim()}`.trim();
    };

    const passwordRules = (val) => ({
      length: val.length >= 8,
      lowercase: /[a-z]/.test(val),
      uppercase: /[A-Z]/.test(val),
      number: /[0-9]/.test(val),
      symbol: /[^A-Za-z0-9]/.test(val),
    });

    const checkStrength = (val) => {
      const rules = passwordRules(val);
      const score = Object.values(rules).filter(Boolean).length;
      const level = score === 0 ? null : score <= 2 ? 'weak' : score === 3 ? 'fair' : score === 4 ? 'good' : 'strong';
      const names = { weak: 'Weak', fair: 'Fair', good: 'Good', strong: 'Strong' };
      const widths = { weak: '25%', fair: '50%', good: '75%', strong: '100%' };

      Object.entries(rules).forEach(([rule, isMet]) => {
        const item = document.querySelector(`[data-password-rule="${rule}"]`);
        item?.classList.toggle('is-met', isMet);
        const state = item?.querySelector('[data-rule-state]');
        if (state) state.textContent = isMet ? ' (met)' : ' (not yet met)';
      });

      strengthLabel.className = 'pwd-strength-label' + (level ? ` is-${level}` : '');
      strengthLabel.querySelector('strong').textContent = level ? names[level] : '—';
      strengthFill.className = 'pwd-strength-fill' + (level ? ` is-${level}` : '');
      strengthFill.style.width = level ? widths[level] : '0';
    };

    const setError = (field, message) => {
      const slot = document.getElementById(`${field.id}-error`);
      field.classList.toggle('is-invalid', Boolean(message));
      if (message) field.setAttribute('aria-invalid', 'true'); else field.removeAttribute('aria-invalid');
      if (slot) {
        slot.textContent = message || '';
        slot.hidden = ! message;
      }
    };

    const validators = new Map([
      [firstName, () => firstName.value.trim() ? '' : 'Please enter your first name.'],
      [lastName, () => lastName.value.trim() ? '' : 'Please enter your last name.'],
      [email, () => {
        const value = email.value.trim();
        if (! value) return 'Please enter your email address.';
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value) ? '' : 'Please enter a valid email address.';
      }],
      [manualBarangay, () => organization.value || manualBarangay.value ? '' : 'Please select your barangay.'],
      [password, () => {
        if (! password.value) return 'Please create a password.';
        return Object.values(passwordRules(password.value)).every(Boolean) ? '' : 'Your password must meet all the requirements below.';
      }],
      [confirmPassword, () => {
        if (! confirmPassword.value) return 'Please re-enter your password.';
        return confirmPassword.value === password.value ? '' : 'Passwords do not match.';
      }],
      [captchaAnswer, () => captchaAnswer.value.trim() ? '' : 'Please enter the code shown in the image.'],
      [agreeTerms, () => agreeTerms.checked ? '' : 'Please agree to the Terms of Use and Privacy Policy to continue.'],
    ]);

    const validateField = (field) => {
      const message = validators.get(field)();
      setError(field, message);
      return ! message;
    };

    // Match feedback appears only once the user has typed a confirmation.
    const syncConfirmFeedback = () => {
      if (! confirmPassword.value) {
        confirmSuccess.hidden = true;
        return;
      }
      const matches = confirmPassword.value === password.value;
      setError(confirmPassword, matches ? '' : 'Passwords do not match.');
      confirmSuccess.hidden = ! matches;
    };

    validators.forEach((validate, field) => {
      const event = field === agreeTerms || field.tagName === 'SELECT' ? 'change' : 'input';
      field.addEventListener(event, () => {
        if (field.classList.contains('is-invalid')) validateField(field);
      });
      field.addEventListener('blur', () => {
        if (field.type !== 'checkbox' && field.value.trim()) validateField(field);
      });
    });

    const bindToggle = (buttonId, input) => {
      const button = document.getElementById(buttonId);
      button?.addEventListener('click', () => {
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        button.setAttribute('aria-pressed', String(isPassword));
        button.querySelector('.pwd-toggle-slash').toggleAttribute('hidden', ! isPassword);
      });
    };

    const syncFarmLocation = () => {
      const selected = organization?.selectedOptions?.[0];
      const barangay = selected?.dataset?.barangay || '';
      const hasOrganization = Boolean(organization?.value);

      assignedBarangay.value = barangay;
      assignedBarangayGroup.style.display = hasOrganization ? '' : 'none';
      manualBarangayGroup.style.display = hasOrganization ? 'none' : '';
      manualBarangay.disabled = hasOrganization;
      manualBarangay.required = ! hasOrganization;
      if (hasOrganization) setError(manualBarangay, '');
    };

    firstName.addEventListener('input', syncName);
    lastName.addEventListener('input', syncName);
    email.addEventListener('blur', () => { email.value = email.value.trim().toLowerCase(); });
    password.addEventListener('input', () => {
      checkStrength(password.value);
      syncConfirmFeedback();
    });
    confirmPassword.addEventListener('input', syncConfirmFeedback);
    organization.addEventListener('change', syncFarmLocation);

    // After a failed submission only the combined name comes back if the split fields were not posted.
    if (! firstName.value && ! lastName.value && name.value) {
      const parts = name.value.trim().split(/\s+/);
      lastName.value = parts.length > 1 ? parts.pop() : '';
      firstName.value = parts.join(' ');
    }
    syncName();
    checkStrength(password.value || '');
    syncFarmLocation();
    bindToggle('pwdToggle', password);
    bindToggle('confirmPwdToggle', confirmPassword);

    form.addEventListener('submit', (event) => {
      syncName();
      email.value = email.value.trim().toLowerCase();

      const invalid = [...validators.keys()].filter((field) => ! validateField(field));
      confirmSuccess.hidden = confirmPassword.classList.contains('is-invalid') || ! confirmPassword.value;

      if (invalid.length) {
        event.preventDefault();
        formMessage.hidden = false;
        invalid[0].focus();
        return;
      }

      formMessage.hidden = true;
      submit.disabled = true;
      submit.innerHTML = '<span class="btn-spinner" aria-hidden="true"></span>Creating account...';
    });

    // Returning with the Back button restores the page from cache: make the button usable again.
    window.addEventListener('pageshow', (event) => {
      if (! event.persisted) return;
      submit.disabled = false;
      submit.textContent = 'Create account';
    });
  });
</script>
</body>
</html>
