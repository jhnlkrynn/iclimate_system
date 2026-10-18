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
@include('auth.partials.register-form-styles')

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
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { transition-duration: .01ms !important; }
      .btn-spinner { animation-duration: 2s; }
    }

    /* -- BIGGER TEXT (page): the form itself grows in auth/partials/register-form-styles.blade.php -- */
    body { font-size: 16px; }
    .reg-notes-title { font-size: 19px; }
    .reg-notes-sub, .perk-item { font-size: 15.5px; }
    .reg-notes-staff { font-size: 14px; }
    .register-page .auth-toggle { max-width: 360px; }
    .register-page .auth-toggle a { height: 38px; font-size: 15px; }
    /* "safe": when the form is taller than the screen it starts at the top instead of being cut off above. */
    .register-page .login-panel { justify-content: safe center; }
    .form-card { max-width: 720px; }
    .reg-foot { font-size: 13.5px; }
    @media (min-width: 1024px) and (min-height: 680px) {
      .form-group > .field-error:not([hidden]), .form-group > .field-success:not([hidden]), .form-group > .field-hint:not([hidden]) { font-size: 12.5px; line-height: 15px; }
    }
  </style>
  @vite('resources/css/accessibility.css')
    @include('partials.global-loader')
</head>
<body class="register-page">
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
        @include('auth.partials.register-form')

        <p class="reg-foot">Already have an account? <a href="{{ route('login') }}">Sign in</a><span class="reg-foot-credit"> &middot; &copy; 2026 iClimate Research Group &middot; Batangas State University ARASOF-Nasugbu</span></p>
      </div>
    </main>
  </div>

@include('auth.partials.register-form-script')
</body>
</html>
