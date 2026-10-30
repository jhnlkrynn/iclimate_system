{{-- Styles of the farmer sign-up form (auth/partials/register-form.blade.php), shared by /register and the landing
     page card. Output inside a <style> element. Page layout (split screen, card width) stays with each page. --}}
    :root {
      --reg-green-dark: var(--sage-800);
      --reg-green: var(--sage-700);
      --reg-mint: var(--sage-300);
      --reg-green-bg: var(--sage-50);
      --reg-border: var(--sage-100);
      --reg-input-border: var(--sage-300);
      --reg-text: var(--palette-ink);
      --reg-text-2: #4D5C50;
      --reg-error: #B42318;
      --reg-success: #16794A;
      --reg-sans: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    }

    .reg-head { display: flex; align-items: center; justify-content: center; gap: 10px; min-height: 30px; margin-bottom: 12px; }
    .reg-head img { height: 30px; width: auto; display: block; flex-shrink: 0; }
    .reg-sub { font-size: 0.875rem; color: var(--reg-text-2); }
    /* After a failed submit the summary takes the subtitle's place, so nothing below it moves. */
    #formMessage { padding: 3px 12px; font-size: 0.8125rem; color: var(--reg-error); background: #FEF3F2; border: 1px solid #F4C7C3; border-radius: 980px; }
    #formMessage[hidden] { display: none; }
    .reg-head:has(#formMessage:not([hidden])) .reg-sub { display: none; }

    .register-form, .form-section { display: flex; flex-direction: column; gap: 14px; }
    .form-row-2col { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .form-group { position: relative; min-width: 0; }
    .form-group label { display: block; font-size: 0.7812rem; line-height: 16px; font-weight: 500; color: var(--reg-text-2); margin-bottom: 4px; }

    .input-wrap { position: relative; }
    .form-input {
      width: 100%; min-width: 0; height: 40px; background-color: #fff; border: 1.5px solid var(--reg-input-border); border-radius: 10px;
      padding: 0 12px; font-size: 0.875rem; color: var(--reg-text); outline: none;
      transition: border-color .15s ease, box-shadow .15s ease, background-color .15s ease;
    }
    .form-input.has-icon { padding-left: 36px; }
    .form-input.has-toggle { padding-right: 40px; }
    .form-input::placeholder { color: #98A39D; }
    .form-input:hover:not(:focus):not(:focus-within):not(:disabled):not(.is-invalid) { border-color: var(--sage-300); }
    .form-input:focus { background-color: #fff; border-color: var(--reg-green); box-shadow: 0 0 0 3px var(--sage-200); }
    .form-input:disabled { color: var(--reg-text-2); background-color: var(--reg-green-bg); }
    .form-input.is-invalid { border-color: var(--reg-error); background-color: #fff; }
    .form-input.is-invalid:focus { box-shadow: 0 0 0 4px rgba(180,35,24,.1); }
    select.form-input {
      appearance: none; -webkit-appearance: none; padding-right: 32px; cursor: pointer;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 16 16' fill='none'%3E%3Cpath d='M4 6l4 4 4-4' stroke='%2365736C' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
      background-repeat: no-repeat; background-position: right 10px center;
    }
    .input-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); pointer-events: none; color: #7C8B83; }
    /* A 44px tap target (wider than the 40px field, but transparent); the icon stays 20px in from the field's right edge. */
    .pwd-toggle {
      position: absolute; right: -2px; top: 50%; transform: translateY(-50%); width: 44px; height: 44px; padding: 0;
      display: flex; align-items: center; justify-content: center;
      color: var(--reg-text-2); background: none; border: none; border-radius: 8px; cursor: pointer;
    }
    .pwd-toggle:hover { color: var(--reg-green); }
    .pwd-toggle-slash[hidden] { display: none; }
    .pwd-toggle:focus-visible, .form-card a:focus-visible, .terms-row input:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 2px; }

    .field-hint { font-size: 0.75rem; color: var(--reg-text-2); margin-top: 4px; }
    .field-error, .field-success { display: flex; align-items: flex-start; gap: 5px; font-size: 0.75rem; line-height: 1.35; margin-top: 4px; }
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
    .org-ms:focus-within { background-color: #fff; border-color: var(--reg-green); box-shadow: 0 0 0 3px var(--sage-200); }
    .org-ms.is-invalid:focus-within { border-color: var(--reg-error); box-shadow: 0 0 0 4px rgba(180,35,24,.1); }
    .org-ms-search { flex: 1 1 80px; min-width: 80px; height: 100%; padding: 0; border: 0; outline: 0; background: transparent; font-size: 0.875rem; color: var(--reg-text); }
    .org-ms-search::placeholder { color: #98A39D; }
    .org-ms-chips { display: flex; gap: 4px; flex: 0 1 auto; min-width: 0; overflow: hidden; margin: 0; padding: 0; list-style: none; }
    .org-ms-chips:empty { display: none; }
    .org-ms-chip { flex: none; display: inline-flex; align-items: center; gap: 2px; height: 26px; padding: 0 2px 0 9px; font-size: 0.7812rem; color: var(--reg-green-dark); background: var(--sage-100); border-radius: 980px; }
    .org-ms-chip[hidden] { display: none; }
    .org-ms-chip span { max-width: 170px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .org-ms-chip button { width: 20px; height: 20px; padding: 0; flex-shrink: 0; display: grid; place-items: center; color: inherit; background: none; border: none; border-radius: 50%; cursor: pointer; }
    .org-ms-chip button:hover { background: rgb(var(--sage-800-rgb) / .1); }
    .org-ms-chip button:focus-visible, .org-ms-more:focus-visible { outline: 2px solid var(--reg-green); outline-offset: 1px; }
    .org-ms-more { flex: none; height: 26px; padding: 0 9px; font: inherit; font-size: 0.7812rem; font-weight: 500; color: var(--reg-green-dark); background: #fff; border: 1px solid var(--sage-200); border-radius: 980px; cursor: pointer; white-space: nowrap; }
    .org-ms-more[hidden] { display: none; }
    /* The list floats over the form (it never pushes it down), and opens upward when there is no room below. */
    .org-ms-list {
      position: absolute; z-index: 20; left: -1px; right: -1px; top: calc(100% + 6px); margin: 0; padding: 6px; list-style: none; cursor: default;
      max-height: 240px; overflow-y: auto; background: #fff; border: 1px solid var(--reg-border); border-radius: 12px;
      box-shadow: 0 12px 28px rgb(var(--sage-700-rgb) / .14);
    }
    .org-ms.is-up .org-ms-list { top: auto; bottom: calc(100% + 6px); }
    .org-ms-list[hidden] { display: none; }
    .org-ms-option { display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 0.8438rem; line-height: 1.35; cursor: pointer; }
    .org-ms-option.is-active { background: var(--reg-green-bg); }
    .org-ms-check { width: 16px; height: 16px; flex-shrink: 0; display: grid; place-items: center; border: 1.5px solid #A9B8B0; border-radius: 4px; color: transparent; background: #fff; }
    .org-ms-option[aria-selected="true"] .org-ms-check { background: var(--reg-green); border-color: var(--reg-green); color: #fff; }
    .org-ms-text { min-width: 0; }
    .org-ms-barangay { color: var(--reg-text-2); font-size: 0.7812rem; }
    .org-ms-empty { padding: 10px; font-size: 0.8438rem; color: var(--reg-text-2); }

    /* -- PASSWORD STRENGTH: one line ---------------------- */
    .pwd-helper { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 12px; }
    .pwd-strength-meta { display: flex; align-items: center; gap: 8px; }
    /* Fixed width, so the pills never move when the word changes. */
    .pwd-strength-label { min-width: 98px; font-size: 0.7812rem; color: var(--reg-text-2); white-space: nowrap; }
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
    .pwd-guide-item { display: inline-flex; align-items: center; gap: 3px; height: 22px; padding: 0 8px 0 6px; border-radius: 980px; font-size: 0.75rem; color: var(--reg-text-2); background: var(--reg-green-bg); white-space: nowrap; }
    .pwd-guide-item svg { flex-shrink: 0; color: transparent; }
    .pwd-guide-item.is-met { color: var(--reg-success); background: var(--sage-100); font-weight: 500; }
    .pwd-guide-item.is-met svg { color: currentColor; }

    /* -- SECURITY CHECK: label, picture and answer on one line -- */
    .captcha-row { display: grid; grid-template-columns: auto auto minmax(0, 1fr); align-items: center; gap: 12px; }
    .captcha-row > label { display: flex; align-items: center; gap: 6px; font-size: 0.7812rem; font-weight: 500; color: var(--reg-text-2); white-space: nowrap; }
    .captcha-row > label svg { color: var(--reg-green); }
    .captcha-image { display: block; width: 110px; height: 38px; object-fit: contain; border: 1px solid var(--reg-input-border); border-radius: 8px; background: #fff; }
    .captcha-field .form-input { letter-spacing: .12em; }
    .captcha-field .form-input::placeholder { letter-spacing: normal; }

    /* -- AGREEMENT + SUBMIT -------------------------------- */
    .terms-row { display: flex; align-items: center; gap: 8px; }
    .terms-row input { width: 16px; height: 16px; margin: 0; flex-shrink: 0; accent-color: var(--reg-green); cursor: pointer; }
    .terms-row label { font-size: 0.8438rem; line-height: 1.4; color: var(--reg-text); cursor: pointer; }
    .terms-row a, .reg-foot a { color: var(--reg-green); font-weight: 500; text-decoration: none; border-radius: 3px; }
    .terms-row a:hover, .reg-foot a:hover { text-decoration: underline; text-underline-offset: 3px; }
    #agreeTerms-error { padding-left: 24px; }

    .btn-submit {
      width: 100%; height: 44px; display: flex; align-items: center; justify-content: center; gap: 10px;
      background: var(--sage-200); color: var(--sage-800); border: 2px solid var(--sage-300); border-radius: 10px;
      font-size: 0.9375rem; font-weight: 700; cursor: pointer; transition: background-color .15s ease;
    }
    .btn-submit:hover { background: var(--sage-500); }
    .btn-submit:focus-visible { outline: 3px solid rgb(var(--sage-700-rgb) / .45); outline-offset: 2px; }
    .btn-submit:disabled { opacity: .8; cursor: wait; }
    .btn-spinner { width: 16px; height: 16px; border-radius: 50%; border: 2px solid rgb(var(--sage-800-rgb) / .25); border-top-color: var(--sage-800); animation: regSpin .8s linear infinite; }
    @keyframes regSpin { to { transform: rotate(360deg); } }

    @media (max-width: 560px) {
      .form-row-2col { grid-template-columns: minmax(0, 1fr); }
      .reg-head { flex-direction: column; gap: 6px; text-align: center; }
      .captcha-row { grid-template-columns: auto minmax(0, 1fr); }
      .captcha-row > label { grid-column: 1 / -1; }
    }

    /* -- BIGGER TEXT: easier to read; same layout as above, every size one step up -- */
    .reg-head { min-height: 36px; }
    .reg-head img { height: 36px; }
    .reg-sub { font-size: 1rem; }
    #formMessage { font-size: 0.9062rem; padding: 4px 14px; }
    .register-form, .form-section { gap: 16px; }
    .form-row-2col { gap: 14px; }
    .form-group label { font-size: 0.875rem; line-height: 18px; margin-bottom: 5px; }
    .form-input { height: 46px; font-size: 1rem; }
    .form-input.has-icon { padding-left: 40px; }
    .org-ms-search { font-size: 1rem; }
    .org-ms-chip, .org-ms-more { height: 30px; font-size: 0.875rem; }
    .org-ms-option, .org-ms-empty { font-size: 0.9375rem; }
    .org-ms-barangay { font-size: 0.875rem; }
    .field-hint, .field-error, .field-success { font-size: 0.8438rem; }
    .pwd-strength-label { min-width: 104px; font-size: 0.875rem; }
    .pwd-strength-track { width: 64px; }
    .pwd-guide-item { height: 26px; padding: 0 10px 0 7px; font-size: 0.8438rem; }
    .captcha-row > label { font-size: 0.875rem; }
    .captcha-image { width: 124px; height: 44px; }
    .terms-row input { width: 18px; height: 18px; }
    .terms-row label { font-size: 0.9375rem; }
    #agreeTerms-error { padding-left: 26px; }
    .btn-submit { height: 50px; font-size: 1.0625rem; }

    /* Room for the eye button and the select chevron (at least 2.75rem), so text never runs under them; the chevron is
       16px (the landing page's form plugin would otherwise draw it at 24px). Placeholders are short enough to fit. */
    .form-input.has-toggle { padding-right: 2.75rem; }
    select.form-input { padding-right: 2.75rem; background-size: 16px 16px; text-overflow: clip; }
    .org-ms { padding-right: 2.75rem; }
