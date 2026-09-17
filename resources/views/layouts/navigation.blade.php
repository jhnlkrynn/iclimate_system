@php
    $user = auth()->user();
    $initials = collect(explode(' ', trim($user->name)))->filter()->map(fn ($part) => mb_strtoupper($part[0]))->take(2)->implode('');
@endphp
<style>
    .topbar-user { display: flex; align-items: center; gap: .7rem; min-width: 0; }
    .topbar-avatar {
        width: 40px;
        height: 40px;
        flex-shrink: 0;
        border-radius: 10px;
        display: grid;
        place-items: center;
        background: linear-gradient(135deg, rgba(143,175,154,.4), rgba(143,175,154,.12));
        border: 1px solid rgba(255,255,255,.2);
        color: #fff;
        font-family: 'DM Mono', monospace;
        font-weight: 700;
        font-size: .82rem;
        letter-spacing: .02em;
    }
    .topbar-user-meta { min-width: 0; }
    .topbar-eyebrow {
        font-family: 'Inter', sans-serif;
        font-size: .7rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: rgba(255,255,255,.72);
        line-height: 1.3;
    }
    .topbar-name {
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        font-size: .98rem;
        letter-spacing: -.005em;
        color: #fff;
        line-height: 1.32;
        max-width: min(38vw, 320px);
    }
    .topbar-role-badge {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        border-radius: 999px;
        padding: .4rem .8rem;
        background: rgba(143,175,154,.14);
        border: 1px solid rgba(143,175,154,.3);
        color: rgba(255,255,255,.88);
        font-family: 'Inter', sans-serif;
        font-size: .74rem;
        font-weight: 600;
        letter-spacing: .01em;
        white-space: nowrap;
    }
    .topbar-role-badge::before { content: ""; width: 6px; height: 6px; border-radius: 999px; background: var(--iclimate-accent, #8FAF9A); flex-shrink: 0; }
    .topbar-actions { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; justify-content: flex-end; }
    .topbar-btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        border-radius: 8px;
        padding: .5rem .9rem;
        font-family: 'Inter', sans-serif;
        font-size: .82rem;
        font-weight: 600;
        border: 1px solid rgba(255,255,255,.18);
        background: rgba(255,255,255,.05);
        color: rgba(255,255,255,.85);
        text-decoration: none;
        cursor: pointer;
        transition: background .16s ease, border-color .16s ease, color .16s ease, transform .16s ease;
    }
    .topbar-btn:hover { background: rgba(255,255,255,.14); border-color: rgba(255,255,255,.32); color: #fff; transform: translateY(-1px); }
    .topbar-btn:active { transform: translateY(0); }
    .topbar-btn-logout:hover { background: rgba(184,74,74,.2); border-color: rgba(184,74,74,.45); }
    @media (max-width: 575.98px) {
        .topbar-name { max-width: 52vw; }
        .topbar-btn span { display: none; }
        .topbar-btn { padding: .5rem .6rem; }
    }
</style>
<nav class="topbar navbar navbar-expand px-3 px-lg-4">
    <div class="container-fluid px-0 gap-3 topbar-inner flex-wrap">
        <button class="btn btn-outline-primary d-lg-none fw-bold d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation menu">
            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M3 5h12M3 9h12M3 13h12" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <span>Menu</span>
        </button>
        <div class="me-auto d-flex align-items-center min-w-0">
            <div class="topbar-user">
                <div class="topbar-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
                <div class="topbar-user-meta d-none d-sm-block min-w-0">
                    <div class="topbar-eyebrow">Signed in as</div>
                    <div class="topbar-name text-truncate">{{ $user->name }}</div>
                </div>
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center me-2">
            <span class="topbar-role-badge">{{ $user->roleLabel() }}</span>
        </div>
        <div class="topbar-actions">
            @unless($user->role === \App\Models\User::ROLE_FARMER)
                <a class="topbar-btn d-none d-sm-inline-flex" href="{{ route('profile.edit') }}">
                    <svg width="15" height="15" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span>Profile</span>
                </a>
            @endunless
            <form method="POST" action="{{ route('logout') }}" data-logout-confirm="Are you sure you want to log out?" data-loading="true">
                @csrf
                <button class="topbar-btn topbar-btn-logout" type="submit" data-loading-text="Logging out...">
                    <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M7 2.5H4a1.5 1.5 0 0 0-1.5 1.5v10A1.5 1.5 0 0 0 4 15.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 5.5l3.5 3.5-3.5 3.5M14.3 9H6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</nav>
