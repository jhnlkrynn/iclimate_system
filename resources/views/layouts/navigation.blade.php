@php
    $user = auth()->user();
    $unreadCount = \Illuminate\Support\Facades\Cache::remember(
        'sidebar:unread-notifications:'.$user->id,
        now()->addSeconds(20),
        fn () => $user->userNotifications()->where('is_read', false)->count()
    );
@endphp
<nav class="topbar navbar navbar-expand px-3 px-lg-4">
    <div class="container-fluid px-0 gap-3 topbar-inner">
        <button class="btn btn-outline-primary d-lg-none fw-bold" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-label="Open navigation menu">
            Menu
        </button>
        <div class="me-auto d-flex align-items-center gap-3">
            <div class="d-none d-sm-block">
                <div class="small text-muted">Signed in as</div>
                <div class="fw-bold">{{ auth()->user()->name }}</div>
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center gap-2 me-2">
            <span class="badge text-bg-light border text-muted">{{ auth()->user()->roleLabel() }}</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a class="btn btn-sm btn-outline-primary position-relative" href="{{ route('notifications.index') }}" aria-label="Open notifications">
                Alerts
                @if ($unreadCount > 0)
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">{{ $unreadCount }}</span>
                @endif
            </a>
            <a class="btn btn-sm btn-outline-secondary d-none d-sm-inline-flex" href="{{ route('profile.edit') }}">Profile</a>
            <form method="POST" action="{{ route('logout') }}" data-loading="true">
                @csrf
                <button class="btn btn-sm btn-outline-danger" type="submit" data-loading-text="Logging out...">Logout</button>
            </form>
        </div>
    </div>
</nav>
