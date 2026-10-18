{{-- The one Log out form: a POST with CSRF to the logout route, behind the "Log out of iClimate?" confirmation
     (resources/js/app.js, bindLogoutConfirmations). Farmers find it at the bottom of the menu drawer
     (layouts/sidebar.blade.php); MAO and IT in the account menu (layouts/navigation.blade.php).
     $variant: 'menu' (account-menu item) or 'sidebar' (a drawer row: icon + label). $label: the button text. --}}
@php
    $logoutIcon = '<svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M7 2.5H4a1.5 1.5 0 0 0-1.5 1.5v10A1.5 1.5 0 0 0 4 15.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 5.5l3.5 3.5-3.5 3.5M14.3 9H6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
@endphp
<form method="POST" action="{{ route('logout') }}" data-logout-confirm="Are you sure you want to log out?" data-loading="true" @if ($variant === 'sidebar') class="ic-sb-logout-form" @endif>
    @csrf
    @if ($variant === 'sidebar')
        <button class="sidebar-link ic-sb-logout" type="submit" data-loading-text="Logging out..." aria-label="{{ $label }}" title="{{ $label }}">
            <span class="sidebar-icon" aria-hidden="true">{!! $logoutIcon !!}</span>
            <span class="sidebar-label">{{ $label }}</span>
        </button>
    @else
        <button class="dropdown-item ic-account-logout" type="submit" data-loading-text="Logging out...">
            {!! $logoutIcon !!}
            {{ $label }}
        </button>
    @endif
</form>
