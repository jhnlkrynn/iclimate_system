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
      --reg-green-dark: #1F3D22;
      --reg-green: #2F6B34;
      --reg-mint: #7FD6B5;
      --reg-green-bg: #F3FAF0;
      --reg-border: #DCE8D6;
      --reg-input-border: #B5C9AE;
      --reg-text: #1E2B21;
      --reg-text-2: #4D5C50;
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

    /* -- GREEN SIDE: what a farmer account gives you ------ */
    .reg-notes-title { font-size: 17px; font-weight: 700; color: var(--reg-green-dark); }
    .reg-notes-sub { font-size: 14px; color: var(--reg-text-2); margin-top: 2px; }
    .perk-list { list-style: none; margin: 18px 0 0; padding: 18px 0 0; border-top: 1px solid var(--reg-border); display: flex; flex-direction: column; gap: 10px; }
    .perk-item { display: flex; align-items: flex-start; gap: 10px; font-size: 14px; line-height: 1.45; color: var(--reg-text); }
    .perk-item svg { flex-shrink: 0; width: 16px; height: 16px; margin-top: 2px; color: var(--reg-green); }
    .reg-notes-staff { margin-top: 18px; padding-top: 16px; border-top: 1px solid var(--reg-border); font-size: 13px; line-height: 1.5; color: var(--reg-text-2); }

    /* -- FORM SIDE: the whole form fits one laptop screen -- */
    /* The green side is narrower on this page so the form can use two columns. */
    .register-page .login-split { grid-template-columns: minmax(0, 39fr) minmax(0, 61fr); }
    .register-page .login-visual { gap: 22px; }
    .register-page .login-panel { justify-content: center; padding: 12px 32px 8px; }
    .register-page .auth-toggle { max-width: 320px; margin-bottom: 10px; padding: 3px; }
    .register-page .auth-toggle a { height: 32px; font-size: 13px; }

    .form-card { width: 100%; max-width: 680px; }
    .reg-head { display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 30px; margin-bottom: 12px; }
    .reg-head img { height: 30px; width: auto; display: block; flex-shrink: 0; }
    .reg-sub { font-size: 14px; color: var(--reg-text-2); }
    /* After a failed submit the summary takes the subtitle's place, so nothing below it moves. */
    #formMessage { padding: 3px 12px; font-size: 13px; color: var(--reg-error); background: #FEF3F2; border: 1px solid #F4C7C3; border-radius: 980px; }
    #formMessage[hidden] { display: none; }
    .reg-head:has(#formMessage:not([hidden])) .reg-sub { display: none; }

    .register-form, .form-section { display: flex; flex-direction: column; gap: 14px; }
    .form-row-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .form-group { position: relative; min-width: 0; }
    .form-group label { display: block; font-size: 12.5px; line-height: 16px; font-weight: 500; color: var(--reg-text-2); margin-bottom: 4px; }

    .input-wrap { position: relative; }
    .form-input {
      width: 100%; min-width: 0; height: 40px; background-color: #fff; border: 1.5px solid var(--reg-input-border); border-radius: 10px;
      padding: 0 12px; font-size: 14px; color: var(--reg-text); outline: none;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .form-input.has-icon { padding-left: 36px; }
    .form-input.has-toggle { padding-right: 40px; }
    .form-input::placeholder { color: #98A39D; }
    .form-input:hover:not(:focus):not(:focus-within):not(:disabled):not(.is-invalid) { border-color: #8BC97E; }
    .form-input:focus { background-color: #fff; border-color: var(--reg-green); box-shadow: 0 0 0 3px #C8E6BF; }
    .form-input:disabled { color: var(--reg-text-2); background-color: var(--reg-green-bg); }
    .form-input.is-invalid { border-color: var(--reg-error); background-color: #fff; }
    .form-input.is-invalid:focus { box-shadow: 0 0 0 4px rgba(180,35,24,.1); }
    select.form-input {
      appearance: none; -webkit-appearance: none; padding-right: 32px; cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%2365736C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
    }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #7C8B83; }
    .pwd-toggle {
      position: absolute; right: 5px; top: 50%; transform: translateY(-50%); width: 30px; height: 30px; padding: 0;
      display: flex; align-items: center; justify-content: center;
      color: var(--reg-text-2); background: none; border: none; border-radius: 8px; cursor: pointer;
    }
    .pwd-toggle:hover { color: var(--reg-green); }
    .pwd-toggle-slash[hidden] { display: none; }
    .pwd-toggle:focus-visible, .form-card a:focus-visible, .terms-row input:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 2px; }

    .field-hint { font-size: 12px; color: var(--reg-text-2); margin-top: 4px; }
    .field-error, .field-success { display: flex; align-items: flex-start; gap: 5px; font-size: 12px; line-height: 1.35; margin-top: 4px; }
    .field-error[hidden], .field-success[hidden], .field-hint[hidden] { display: none; }
    .field-error { color: var(--reg-error); }
    .field-success { color: var(--reg-success); }
    .field-error::before, .field-success::before { content: ''; width: 12px; height: 12px; flex-shrink: 0; margin-top: 1px; background-repeat: no-repeat; background-position: center; background-size: 12px; }
    .field-error::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Ccircle cx='7' cy='7' r='6' stroke='%23B42318' stroke-width='1.4'/%3E%3Cpath d='M7 4v3.5' stroke='%23B42318' stroke-width='1.4' stroke-linecap='round'/%3E%3Ccircle cx='7' cy='9.8' r='.8' fill='%23B42318'/%3E%3C/svg%3E"); }
    .field-success::before { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='14' height='14' viewBox='0 0 14 14' fill='none'%3E%3Ccircle cx='7' cy='7' r='6' stroke='%2316794A' stroke-width='1.4'/%3E%3Cpath d='M4.4 7.2l1.8 1.8 3.4-3.8' stroke='%2316794A' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); }

    /* -- OTHER ORGANIZATIONS (searchable multi-select) ----- */
    .field-optional { font-weight: 400; color: #98A39D; }
    select.form-input.org-native { height: auto; padding: 4px 6px; background-image: none; }
    /* One line: the chosen organizations as chips (then "+N more"), and the search box. */
    .org-ms {
      position: relative; display: flex; align-items: center; gap: 6px; padding-right: 34px; cursor: text;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%2365736C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
    }
    .org-ms.is-open { background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 10l4-4 4 4' stroke='%2365736C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E"); }
    .org-ms:focus-within { background-color: #fff; border-color: var(--reg-green); box-shadow: 0 0 0 3px #C8E6BF; }
    .org-ms.is-invalid:focus-within { border-color: var(--reg-error); box-shadow: 0 0 0 4px rgba(180,35,24,.1); }
    .org-ms-search { flex: 1 1 80px; min-width: 80px; height: 100%; padding: 0; border: 0; outline: 0; background: transparent; font-size: 14px; color: var(--reg-text); }
    .org-ms-search::placeholder { color: #98A39D; }
    .org-ms-chips { display: flex; gap: 4px; flex: 0 1 auto; min-width: 0; overflow: hidden; margin: 0; padding: 0; list-style: none; }
    .org-ms-chips:empty { display: none; }
    .org-ms-chip { flex: none; display: inline-flex; align-items: center; gap: 2px; height: 26px; padding: 0 2px 0 9px; font-size: 12.5px; color: var(--reg-green-dark); background: #E2F1EA; border-radius: 980px; }
    .org-ms-chip[hidden] { display: none; }
    .org-ms-chip span { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .org-ms-chip button { width: 20px; height: 20px; padding: 0; flex-shrink: 0; display: grid; place-items: center; color: inherit; background: none; border: none; border-radius: 50%; cursor: pointer; }
    .org-ms-chip button:hover { background: rgba(18,63,50,.1); }
    .org-ms-chip button:focus-visible, .org-ms-more:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 1px; }
    .org-ms-more { flex: none; height: 26px; padding: 0 9px; font: inherit; font-size: 12.5px; font-weight: 500; color: var(--reg-green-dark); background: #fff; border: 1px solid #CFE3D9; border-radius: 980px; cursor: pointer; white-space: nowrap; }
    .org-ms-more[hidden] { display: none; }
    /* The list floats over the form (it never pushes it down), and opens upward when there is no room below. */
    .org-ms-list {
      position: absolute; z-index: 20; left: -1px; right: -1px; top: calc(100% + 6px); margin: 0; padding: 6px; list-style: none; cursor: default;
      max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid var(--reg-border); border-radius: 12px;
      box-shadow: 0 12px 28px rgba(18,63,50,.14);
    }
    .org-ms.is-up .org-ms-list { top: auto; bottom: calc(100% + 6px); }
    .org-ms-list[hidden] { display: none; }
    .org-ms-option { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 13.5px; line-height: 1.35; cursor: pointer; }
    .org-ms-option.is-active { background: var(--reg-green-bg); }
    .org-ms-check { width: 16px; height: 16px; flex-shrink: 0; display: grid; place-items: center; border: 1.5px solid #A9B8B0; border-radius: 4px; color: transparent; background: #fff; }
    .org-ms-option[aria-selected="true"] .org-ms-check { background: var(--reg-green); border-color: var(--reg-green); color: #fff; }
    .org-ms-text { min-width: 0; }
    .org-ms-barangay { color: var(--reg-text-2); font-size: 12.5px; }
    .org-ms-empty { padding: 10px; font-size: 13.5px; color: var(--reg-text-2); }

    /* -- PASSWORD STRENGTH: one line ---------------------- */
    .pwd-helper { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 12px; }
    .pwd-strength-meta { display: flex; align-items: center; gap: 8px; }
    /* Fixed width, so the pills never move when the word changes. */
    .pwd-strength-label { min-width: 98px; font-size: 12.5px; color: var(--reg-text-2); white-space: nowrap; }
    .pwd-strength-label strong { font-weight: 600; color: var(--reg-text); }
    .pwd-strength-label.is-weak strong { color: var(--reg-error); }
    .pwd-strength-label.is-fair strong { color: #8A5A00; }
    .pwd-strength-label.is-good strong { color: var(--reg-green); }
    .pwd-strength-label.is-strong strong { color: var(--reg-success); }
    .pwd-strength-track { width: 56px; height: 4px; border-radius: 2px; background: var(--reg-border); overflow: hidden; }
    .pwd-strength-fill { height: 100%; width: 0; border-radius: 2px; background: var(--reg-border); transition: width .2s ease, background-color .2s ease; }
    .pwd-strength-fill.is-weak { background: var(--reg-error); }
    .pwd-strength-fill.is-fair { background: #C98A1B; }
    .pwd-strength-fill.is-good { background: var(--reg-green); }
    .pwd-strength-fill.is-strong { background: var(--reg-success); }
    .pwd-guide { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; gap: 6px; }
    .pwd-guide-item { display: inline-flex; align-items: center; gap: 3px; height: 22px; padding: 0 8px 0 6px; border-radius: 980px; font-size: 12px; color: var(--reg-text-2); background: var(--reg-green-bg); white-space: nowrap; }
    .pwd-guide-item svg { flex-shrink: 0; color: transparent; }
    .pwd-guide-item.is-met { color: var(--reg-success); background: #E3F3EA; font-weight: 500; }
    .pwd-guide-item.is-met svg { color: currentColor; }

    /* -- SECURITY CHECK: label, picture and answer on one line -- */
    .captcha-row { display: grid; grid-template-columns: auto auto minmax(0, 1fr); align-items: center; gap: 12px; }
    .captcha-row > label { display: flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 500; color: var(--reg-text-2); white-space: nowrap; }
    .captcha-row > label svg { color: var(--reg-green); }
    .captcha-image { display: block; width: 110px; height: 38px; object-fit: contain; border: 1px solid var(--reg-input-border); border-radius: 8px; background: #fff; }
    .captcha-field .form-input { letter-spacing: .12em; }
    .captcha-field .form-input::placeholder { letter-spacing: normal; }

    /* -- AGREEMENT + SUBMIT -------------------------------- */
    .terms-row { display: flex; align-items: center; gap: 8px; }
    .terms-row input { width: 16px; height: 16px; margin: 0; flex-shrink: 0; accent-color: var(--reg-green); cursor: pointer; }
    .terms-row label { font-size: 13.5px; line-height: 1.4; color: var(--reg-text); cursor: pointer; }
    .terms-row a, .reg-foot a { color: var(--reg-green); font-weight: 500; text-decoration: none; border-radius: 3px; }
    .terms-row a:hover, .reg-foot a:hover { text-decoration: underline; text-underline-offset: 3px; }
    #agreeTerms-error { padding-left: 24px; }

    .btn-submit {
      width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; gap: 10px;
      background: #A6D79B; color: #173B1F; border: 2px solid #8BC97E; border-radius: 10px;
      font-size: 15px; font-weight: 700; cursor: pointer; transition: background-color .15s ease;
    }
    .btn-submit:hover { background: #8BC97E; }
    .btn-submit:focus-visible { outline: 3px solid rgba(31,104,79,.45); outline-offset: 2px; }
    .btn-submit:disabled { opacity: .8; cursor: wait; }
    .btn-spinner { width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgba(23,59,31,.25); border-top-color: #173B1F; animation: regSpin .8s linear infinite; }
    @keyframes regSpin { to { transform: rotate(360deg); } }

    .reg-foot { margin-top: 10px; text-align: center; font-size: 12px; line-height: 1.5; color: var(--reg-text-2); }
    .reg-foot-credit { color: var(--reg-text-2); }

    /* Laptop and desktop: messages under a field sit in the gap below it (cut to one line, full text on hover
       and for screen readers), so they never push the rows apart. The page scrolls normally below the header. */
    @media (min-width: 1024px) and (min-height: 680px) {
      .form-group > .field-error:not([hidden]), .form-group > .field-success:not([hidden]), .form-group > .field-hint:not([hidden]) {
        display: block; position: absolute; left: 0; right: 0; top: 100%; margin-top: 1px;
        font-size: 11.5px; line-height: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      }
      .form-group > .field-error::before, .form-group > .field-success::before { display: inline-block; vertical-align: -2px; margin: 0 4px 0 0; width: 11px; height: 11px; background-size: 11px; }
    }

    /* Tablets and phones: the green side is hidden and the page scrolls normally. */
    @media (max-width: 900px) {
      .register-page .login-split { grid-template-columns: minmax(0, 1fr); }
      .register-page .login-visual { display: none; }
      .register-page .login-panel { justify-content: flex-start; padding: 20px 16px 16px; }
    }
    @media (max-width: 560px) {
      .form-row-2col { grid-template-columns: minmax(0, 1fr); }
      .reg-head { flex-direction: column; gap: 6px; text-align: center; }
      .captcha-row { grid-template-columns: auto minmax(0, 1fr); }
      .captcha-row > label { grid-column: 1 / -1; }
    }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; }
      .btn-spinner { animation-duration: 2s; }
    }

    /* -- BIGGER TEXT: easier to read; same layout as above, every size one step up -- */
    body { font-size: 16px; }
    .reg-notes-title { font-size: 19px; }
    .reg-notes-sub, .perk-item { font-size: 15.5px; }
    .reg-notes-staff { font-size: 14px; }
    .register-page .auth-toggle { max-width: 360px; }
    .register-page .auth-toggle a { height: 38px; font-size: 15px; }
    /* "safe": when the form is taller than the screen it starts at the top instead of being cut off above. */
    .register-page .login-panel { justify-content: safe center; }
    .form-card { max-width: 720px; }
    .reg-head { min-height: 36px; }
    .reg-head img { height: 36px; }
    .reg-sub { font-size: 16px; }
    #formMessage { font-size: 14.5px; padding: 4px 14px; }
    .register-form, .form-section { gap: 16px; }
    .form-row-2col { gap: 14px; }
    .form-group label { font-size: 14px; line-height: 18px; margin-bottom: 5px; }
    .form-input { height: 46px; font-size: 16px; }
    .form-input.has-icon { padding-left: 40px; }
    .org-ms-search { font-size: 16px; }
    .org-ms-chip, .org-ms-more { height: 30px; font-size: 14px; }
    .org-ms-option, .org-ms-empty { font-size: 15px; }
    .org-ms-barangay { font-size: 14px; }
    .field-hint, .field-error, .field-success { font-size: 13.5px; }
    .pwd-strength-label { min-width: 104px; font-size: 14px; }
    .pwd-strength-track { width: 64px; }
    .pwd-guide-item { height: 26px; padding: 0 10px 0 7px; font-size: 13.5px; }
    .captcha-row > label { font-size: 14px; }
    .captcha-image { width: 124px; height: 44px; }
    .terms-row input { width: 18px; height: 18px; }
    .terms-row label { font-size: 15px; }
    #agreeTerms-error { padding-left: 26px; }
    .btn-submit { height: 50px; font-size: 17px; }
    .reg-foot { font-size: 13.5px; }
    @media (min-width: 1024px) and (min-height: 680px) {
      .form-group > .field-error:not([hidden]), .form-group > .field-success:not([hidden]), .form-group > .field-hint:not([hidden]) { font-size: 12.5px; line-height: 15px; }
    }
  </style>
  @vite('resources/css/accessibility.css')
    @include('partials.global-loader')
</head>
<body class="register-page">
  @include('partials.gov-strip')
  @include('auth.partials.masthead')
  <div class="login-split">
    <aside class="login-visual">
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
        {{-- The active tab already says "Create account"; the heading stays for screen readers only. --}}
        <h1 class="sr-only">Create account</h1>
        <div class="reg-head">
          <p class="reg-sub">Register as a rice farmer in Lian, Batangas.</p>
          <div id="formMessage" role="alert" @unless($errors->any()) hidden @endunless>Please check the highlighted fields below and try again.</div>
        </div>

        <form method="POST" action="{{ route('register') }}" class="register-form" data-loader-text="Creating account...">
          @csrf
          <input type="hidden" id="name" name="name" value="{{ old('name') }}">

          <section class="form-section" aria-labelledby="sectionPersonal">
            <h2 class="sr-only" id="sectionPersonal">Personal information</h2>

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
            <h2 class="sr-only" id="sectionFarm">Farm information</h2>

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
                 one (it sets the farm location); pick any others here. The plain multi-select is the no-JavaScript
                 fallback; the script below turns it into a searchable dropdown with chips. --}}
            @if ($farmerOrganizations->count() > 1)
              @php($otherOrganizations = array_map('strval', (array) old('other_organizations', [])))
              @php($otherOrganizationsError = $errors->first('other_organizations') ?: $errors->first('other_organizations.*'))
              <div class="form-group" data-org-multiselect>
                <label for="other_organizations">Other organizations you belong to <span class="field-optional">(optional)</span></label>
                <select id="other_organizations" name="other_organizations[]" multiple size="5" class="form-input org-native @if($otherOrganizationsError) is-invalid @endif" aria-describedby="other_organizations-hint other_organizations-error">
                  @foreach($farmerOrganizations as $organization)
                    <option value="{{ $organization->id }}" data-name="{{ $organization->name }}" data-barangay="{{ $organization->barangay }}" @selected(in_array((string) $organization->id, $otherOrganizations, true))>{{ $organization->name }} · {{ $organization->barangay }}</option>
                  @endforeach
                </select>
                <p class="field-hint org-native-hint" id="other_organizations-hint">Hold Ctrl (or Cmd on a Mac) to choose more than one.</p>
                <p class="field-error" id="other_organizations-error" @unless($otherOrganizationsError) hidden @endunless>{{ $otherOrganizationsError }}</p>
              </div>
            @endif
          </section>

          <section class="form-section" aria-labelledby="sectionSecurity">
            <h2 class="sr-only" id="sectionSecurity">Account security</h2>

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
              <img class="captcha-image" width="110" height="38" src="{{ route('captcha.image', ['context' => 'register', 'v' => $captcha['token'] ?? now()->timestamp]) }}" alt="Security code image">
              <div class="form-group captcha-field">
                <input type="text" id="captcha_answer" name="captcha_answer" class="form-input @error('captcha_answer') is-invalid @enderror" placeholder="Enter the code shown" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" aria-describedby="captcha_answer-error" @error('captcha_answer') aria-invalid="true" @enderror required/>
                <p class="field-error" id="captcha_answer-error" @unless($errors->has('captcha_answer')) hidden @endunless>{{ $errors->first('captcha_answer') }}</p>
              </div>
            </div>
          </section>

          <section class="form-section" aria-labelledby="sectionAgreement">
            <h2 class="sr-only" id="sectionAgreement">Agreement</h2>

            <div class="form-group">
              <div class="terms-row">
                <input type="checkbox" id="agreeTerms" aria-describedby="agreeTerms-note agreeTerms-error" required/>
                <label for="agreeTerms" title="Your information is used to provide climate and agricultural services through iClimate.">I agree to the <a href="#">Terms of use</a> and <a href="#">Privacy policy</a>.</label>
              </div>
              <p class="sr-only" id="agreeTerms-note">Your information is used to provide climate and agricultural services through iClimate.</p>
              <p class="field-error" id="agreeTerms-error" hidden></p>
            </div>
          </section>

          <button class="btn-submit" id="createAccountBtn" type="submit">Create account</button>
        </form>

        <p class="reg-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a><span class="reg-foot-credit"> &middot; &copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</span></p>
      </div>
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
    // On a laptop a message is cut to one line; its full text shows on hover.
    document.querySelectorAll('.field-error').forEach((slot) => { slot.title = slot.textContent.trim(); });

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
        slot.title = message || '';
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

    // Other organizations: the hidden native multi-select stays the source of truth (it is what gets submitted); this
    // builds a searchable dropdown with chips on top of it. The main organization is never offered as an "other" one.
    const setupOtherOrganizations = () => {
      const native = document.getElementById('other_organizations');
      if (! native) return () => {};
      const group = native.closest('[data-org-multiselect]');
      const errorSlot = document.getElementById('other_organizations-error');
      const options = [...native.options];
      const placeholder = 'Select other organizations (optional)';
      const svg = (path) => `<svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true"><path d="${path}" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

      // The field looks like the other inputs: icon, the chosen organizations as chips on one line ("+N more" for
      // the ones that do not fit), then the search box. The list floats below it, or above when there is no room.
      const wrap = document.createElement('div');
      wrap.className = 'form-input has-icon org-ms';
      const chips = document.createElement('ul');
      chips.className = 'org-ms-chips';
      chips.setAttribute('aria-label', 'Chosen organizations');
      const more = document.createElement('button');
      Object.assign(more, { type: 'button', className: 'org-ms-more', hidden: true });
      const input = document.createElement('input');
      Object.assign(input, { type: 'text', id: 'other_organizations_search', className: 'org-ms-search', placeholder, autocomplete: 'off', spellcheck: false });
      Object.entries({ role: 'combobox', 'aria-expanded': 'false', 'aria-controls': 'other_organizations_list', 'aria-autocomplete': 'list', 'aria-describedby': 'other_organizations-error' })
        .forEach(([key, value]) => input.setAttribute(key, value));
      if (native.classList.contains('is-invalid')) {
        wrap.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
      }
      const list = document.createElement('ul');
      Object.assign(list, { id: 'other_organizations_list', className: 'org-ms-list', hidden: true });
      list.setAttribute('role', 'listbox');
      list.setAttribute('aria-multiselectable', 'true');
      list.setAttribute('aria-label', 'Other organizations');

      wrap.append(organization.parentElement.querySelector('.input-icon').cloneNode(true), chips, more, input, list);
      native.hidden = true;
      group.querySelector('.org-native-hint').hidden = true;
      group.querySelector('label').htmlFor = input.id;
      native.after(wrap);

      let visible = [];
      let active = -1;
      const isOpen = () => ! list.hidden;

      const renderList = () => {
        const query = input.value.trim().toLowerCase();
        visible = options.filter((option) => ! option.disabled
          && (! query || `${option.dataset.name} ${option.dataset.barangay}`.toLowerCase().includes(query)));
        active = Math.min(active, visible.length - 1);

        list.replaceChildren(...visible.map((option, index) => {
          const item = document.createElement('li');
          item.id = `other_organizations_option_${option.value}`;
          item.className = 'org-ms-option' + (index === active ? ' is-active' : '');
          item.dataset.index = index;
          item.setAttribute('role', 'option');
          item.setAttribute('aria-selected', String(option.selected));
          const check = document.createElement('span');
          check.className = 'org-ms-check';
          check.innerHTML = svg('M2.5 6.2l2.2 2.2 4.8-5');
          const text = document.createElement('span');
          text.className = 'org-ms-text';
          const barangay = document.createElement('span');
          barangay.className = 'org-ms-barangay';
          barangay.textContent = `· ${option.dataset.barangay}`;
          text.append(`${option.dataset.name} `, barangay);
          item.append(check, text);
          return item;
        }));
        if (! visible.length) {
          const empty = document.createElement('li');
          empty.className = 'org-ms-empty';
          empty.textContent = 'No organization matches your search.';
          list.append(empty);
        }

        // Scroll only the list (never the page) to keep the highlighted option in view.
        const current = list.querySelector('.is-active');
        if (current) {
          input.setAttribute('aria-activedescendant', current.id);
          if (current.offsetTop < list.scrollTop) list.scrollTop = current.offsetTop;
          else if (current.offsetTop + current.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = current.offsetTop + current.offsetHeight - list.clientHeight;
        } else {
          input.removeAttribute('aria-activedescendant');
        }
      };

      // Chips stay on one line: the ones that do not fit are hidden and counted in "+N more".
      const fitChips = () => {
        const items = [...chips.children];
        let shown = items.length;
        const showMore = () => {
          more.hidden = shown === items.length;
          more.textContent = shown ? `+${items.length - shown} more` : `${items.length} chosen`;
          more.setAttribute('aria-label', `${items.length} organizations chosen. Show the list.`);
        };
        items.forEach((item) => { item.hidden = false; });
        showMore();
        while (shown > 0 && chips.scrollWidth > chips.clientWidth + 1) {
          items[--shown].hidden = true;
          showMore();
        }
        input.placeholder = items.length ? '' : placeholder;
      };

      const renderChips = () => {
        chips.replaceChildren(...options.filter((option) => option.selected).map((option) => {
          const chip = document.createElement('li');
          chip.className = 'org-ms-chip';
          const name = document.createElement('span');
          name.textContent = option.dataset.name;
          name.title = `${option.dataset.name} · ${option.dataset.barangay}`;
          const remove = document.createElement('button');
          remove.type = 'button';
          remove.setAttribute('aria-label', `Remove ${option.dataset.name}`);
          remove.innerHTML = svg('M3 3l6 6M9 3l-6 6');
          remove.addEventListener('click', () => {
            toggle(option, false);
            input.focus();
          });
          chip.append(name, remove);
          return chip;
        }));
        fitChips();
      };

      const toggle = (option, on = ! option.selected) => {
        option.selected = on;
        wrap.classList.remove('is-invalid');
        input.removeAttribute('aria-invalid');
        if (errorSlot) errorSlot.hidden = true;
        renderChips();
        if (isOpen()) renderList();
      };

      const open = () => {
        if (! isOpen()) {
          const box = wrap.getBoundingClientRect();
          const below = window.innerHeight - box.bottom;
          wrap.classList.toggle('is-up', below < 260 && box.top > below);
          list.hidden = false;
          wrap.classList.add('is-open');
          input.setAttribute('aria-expanded', 'true');
        }
        renderList();
      };

      const close = () => {
        list.hidden = true;
        active = -1;
        wrap.classList.remove('is-open');
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
      };

      const move = (step) => {
        if (! isOpen()) return open();
        if (! visible.length) return;
        active = active < 0 ? (step > 0 ? 0 : visible.length - 1) : (active + step + visible.length) % visible.length;
        renderList();
      };

      // Pressing anywhere in the field keeps the focus in the search box, so the list stays open for picking several.
      wrap.addEventListener('mousedown', (event) => {
        if (event.target !== input) event.preventDefault();
      });
      wrap.addEventListener('click', (event) => {
        const item = event.target.closest('.org-ms-option');
        if (item) {
          active = Number(item.dataset.index);
          toggle(visible[active]);
          return;
        }
        if (list.contains(event.target) || event.target.closest('.org-ms-chip button')) return;
        input.focus();
        open();
      });
      input.addEventListener('input', () => {
        active = 0;
        open();
      });
      input.addEventListener('blur', close);
      input.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
          event.preventDefault();
          move(event.key === 'ArrowDown' ? 1 : -1);
        } else if (event.key === 'Enter') {
          // Enter picks an organization; it never submits the form from this field.
          event.preventDefault();
          if (isOpen() && visible[active]) toggle(visible[active]); else open();
        } else if (event.key === 'Escape' && isOpen()) {
          event.preventDefault();
          close();
        } else if (event.key === 'Backspace' && ! input.value) {
          const last = options.filter((option) => option.selected).pop();
          if (last) toggle(last, false);
        }
      });
      if ('ResizeObserver' in window) new ResizeObserver(fitChips).observe(wrap);

      // Called whenever the main organization changes: it leaves the options and the chips.
      return () => {
        options.forEach((option) => {
          option.disabled = option.value === organization.value;
          if (option.disabled) option.selected = false;
        });
        renderChips();
        if (isOpen()) renderList();
      };
    };
    const syncOtherOrganizations = setupOtherOrganizations();

    firstName.addEventListener('input', syncName);
    lastName.addEventListener('input', syncName);
    email.addEventListener('blur', () => { email.value = email.value.trim().toLowerCase(); });
    password.addEventListener('input', () => {
      checkStrength(password.value);
      syncConfirmFeedback();
    });
    confirmPassword.addEventListener('input', syncConfirmFeedback);
    organization.addEventListener('change', () => {
      syncFarmLocation();
      syncOtherOrganizations();
    });

    // After a failed submission only the combined name comes back if the split fields were not posted.
    if (! firstName.value && ! lastName.value && name.value) {
      const parts = name.value.trim().split(/\s+/);
      lastName.value = parts.length > 1 ? parts.pop() : '';
      firstName.value = parts.join(' ');
    }
    syncName();
    checkStrength(password.value || '');
    syncFarmLocation();
    syncOtherOrganizations();
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
