@php
    $user = auth()->user();
@endphp
<nav class="topbar navbar navbar-expand px-3 px-lg-4">
    <div class="container-fluid px-0 gap-3 topbar-inner flex-wrap">
        <button class="btn btn-outline-primary d-lg-none fw-bold" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation menu">
            Menu
        </button>
        <div class="me-auto d-flex align-items-center gap-3 min-w-0">
            <div class="d-none d-sm-block min-w-0">
                <div class="small text-muted">Signed in as</div>
                <div class="fw-bold text-truncate" style="max-width: min(38vw, 320px);">{{ auth()->user()->name }}</div>
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center gap-2 me-2">
            <span class="badge text-bg-light border text-muted">{{ auth()->user()->roleLabel() }}</span>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            @unless($user->role === \App\Models\User::ROLE_FARMER)
                <a class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex" href="{{ route('profile.edit') }}">Profile</a>
            @endunless
            <form method="POST" action="{{ route('logout') }}" data-logout-confirm="Are you sure you want to log out?" data-loading="true">
                @csrf
                <button class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-2" type="submit" data-loading-text="Logging out...">
                    <svg width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M7 2.5H4a1.5 1.5 0 0 0-1.5 1.5v10A1.5 1.5 0 0 0 4 15.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 5.5l3.5 3.5-3.5 3.5M14.3 9H6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    Logout
                </button>
            </form>
        </div>
    </div>
</nav>
