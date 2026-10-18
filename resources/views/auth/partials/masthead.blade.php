{{-- Header of the sign in and create account pages: the MAO logo, the iClimate logo and the office name,
     as on the landing page's top nav (components/public-nav.blade.php), with a way back to the home page. --}}
<style>
  .auth-masthead { background: #fff; border-bottom: 1px solid #DCE8D6; font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
  .auth-masthead-inner { display: flex; align-items: center; justify-content: space-between; gap: 16px; max-width: 1200px; margin: 0 auto; padding: 10px 24px; }
  .auth-brand { display: flex; align-items: center; gap: 12px; min-width: 0; text-decoration: none; }
  /* The MAO logo is square and transparent: as tall as the iClimate logo beside it, never stretched. */
  .auth-brand-mao { width: 56px; height: 56px; object-fit: contain; flex-shrink: 0; }
  .auth-brand-logo { width: auto; height: 56px; flex-shrink: 0; }
  .auth-brand-text { display: flex; flex-direction: column; min-width: 0; padding-left: 14px; border-left: 1px solid #DCE8D6; }
  .auth-brand-office { font-size: 1.125rem; font-weight: 700; line-height: 1.25; color: #1F3D22; }
  .auth-brand-place { font-size: 1rem; line-height: 1.35; color: #4D5C50; }
  .auth-home-link {
    display: inline-flex; align-items: center; gap: 8px; flex-shrink: 0; min-height: 46px; padding: 0 16px;
    border: 2px solid #8BC97E; border-radius: 10px; background: #fff;
    font-size: 1rem; font-weight: 700; color: #173B1F; text-decoration: none;
  }
  .auth-home-link:hover { background: #E4F3DE; }
  .auth-masthead a:focus-visible { outline: 3px solid #2F6B34; outline-offset: 3px; border-radius: 6px; }
  @media (max-width: 767.98px) {
    .auth-masthead-inner { padding: 8px 16px; }
    .auth-brand-text { display: none; }
    .auth-brand { gap: 8px; }
    .auth-brand-mao { width: 48px; height: 48px; }
    .auth-brand-logo { height: 48px; }
  }
</style>
<header class="auth-masthead">
  <div class="auth-masthead-inner">
    <a href="{{ url('/') }}" class="auth-brand" aria-label="iClimate, Municipal Agriculture Office of Lian, home">
      <img class="auth-brand-mao" src="{{ asset('images/mao-logo.png') }}" alt="Municipal Agriculture Office logo" width="56" height="56">
      <img class="auth-brand-logo" src="{{ asset('images/iclimate-logo.png') }}" alt="" width="70" height="56">
      <span class="auth-brand-text">
        <span class="auth-brand-office">Municipal Agriculture Office</span>
        <span class="auth-brand-place">Municipality of Lian, Batangas</span>
      </span>
    </a>
    <a href="{{ url('/') }}" class="auth-home-link">
      <svg width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M13 8H3M7 12L3 8l4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
      Back to home
    </a>
  </div>
</header>
