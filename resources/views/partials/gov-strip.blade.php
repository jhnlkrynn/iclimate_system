{{-- The blue government strip for the pages outside the signed-in layout (sign in, create account, password pages).
     Same size as the signed-in pages' strip (resources/css/gov-theme.css .gov-strip); change both together. --}}
<style>
  .gov-strip {
    display: flex; flex: 0 0 100%; flex-wrap: wrap; align-items: center; justify-content: center; gap: 2px 12px;
    min-height: 42px; padding: 8px 16px; background: #0038A8; color: #fff;
    font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif;
    font-size: 1rem; line-height: 1.3; letter-spacing: .01em; text-align: center;
  }
  .gov-strip strong { color: #FCD116; font-weight: 700; }
  .gov-strip-sep { opacity: .7; }
  body:has(> .gov-strip) { flex-wrap: wrap; align-content: flex-start; }
  /* The page below fills the rest of the screen, so the strip adds no extra scrolling. */
  .gov-strip ~ :is(.login-split, .login-layout) { min-height: calc(100vh - 42px); }
  @media (max-width: 767.98px) {
    .gov-strip { min-height: 32px; padding: 5px 10px; font-size: .8125rem; }
    .gov-strip ~ :is(.login-split, .login-layout) { min-height: calc(100vh - 32px); }
    .gov-strip span:not(.gov-strip-sep):first-child, .gov-strip .gov-strip-sep:first-of-type { display: none; }
  }
</style>
<div class="gov-strip" role="note">
  <span>Republic of the Philippines</span>
  <span class="gov-strip-sep" aria-hidden="true">·</span>
  <span>Province of Batangas</span>
  <span class="gov-strip-sep" aria-hidden="true">·</span>
  <strong>Municipality of Lian</strong>
</div>
