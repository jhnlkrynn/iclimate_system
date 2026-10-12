{{-- Compact brand row at the top of the sidebar (desktop and phone drawer), the same for every role. --}}
<a class="sidebar-brand ic-sb-brand" href="{{ route(auth()->user()->dashboardRoute()) }}" aria-label="iClimate home">
    <img src="{{ asset('images/iclimate-icon.png') }}" alt="" class="ic-sb-logo ic-logo-for-light" width="34" height="34">
    <img src="{{ asset('images/iclimate-icon-light.png') }}" alt="" class="ic-sb-logo ic-logo-for-dark" width="34" height="34">
    <span class="ic-sb-brand-text">
        <span class="ic-sb-wordmark">iClimate</span>
        <span class="ic-sb-place">Lian, Batangas</span>
    </span>
</a>
