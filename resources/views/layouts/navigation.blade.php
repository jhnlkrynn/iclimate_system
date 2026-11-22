@php
    $user = auth()->user();
    $avatarUrl = $user->avatarUrl();
    $isFarmerNav = $user->role === \App\Models\User::ROLE_FARMER;
    // English / Filipino for the roles that have it (farmers and MAO); IT works in English.
    $navLangRoute = $isFarmerNav ? 'farmer.dashboard.language' : ($user->role === \App\Models\User::ROLE_MAO ? 'mao.dashboard.language' : null);
    $navLocale = $isFarmerNav ? \App\Support\FarmerLanguage::resolve(request()) : ($navLangRoute ? \App\Support\MaoLanguage::of($user) : null);
    $navBarangay = $isFarmerNav ? $user->registeredBarangay() : null;
    // "Good afternoon, Naicel!" by Manila time, in the account's language (the farmer.greeting lines).
    $navNow = now('Asia/Manila');
    $navHour = $navNow->hour;
    $navPeriod = $navHour >= 5 && $navHour < 12 ? 'morning' : ($navHour >= 12 && $navHour < 18 ? 'afternoon' : 'evening');
    $navGreeting = __('farmer.greeting.'.$navPeriod, ['name' => $user->greetingName()], $navLocale ?? 'en');
    $navDate = $navNow->format('D, M j');
    // Every role gets the same bell: it opens their own notifications in the shared pop-up (resources/js/notifications-popup.js).
    $showNavBell = true;
    // The My Farm selector: the farmer's farms (read once per request) and the one their pages show.
    $navFarmsOn = $isFarmerNav && \App\Support\ActiveFarm::available();
    $navFarms = $navFarmsOn ? \App\Support\ActiveFarm::farms($user) : collect();
    $navFarm = $navFarmsOn ? \App\Support\ActiveFarm::current($user) : null;
    $navFarmLabel = $navFarm ? ($navFarm->farm_name.($navFarm->barangay ? ' · '.$navFarm->barangay : '')) : null;
    // The bell and the MAO/IT pending-account badge share one request-local count query.
    $navCounts = $showNavBell ? \App\Support\NavigationCounts::for($user) : ['unread' => 0, 'pending' => 0];
    $navUnread = $navCounts['unread'];
    $navPendingCount = $navCounts['pending'];
    $isMaoNav = $user->isMao();
    $navMaoCoverage = $isMaoNav ? $user->coverageLabel() : null;
    $navMaoView = $isMaoNav ? \App\Support\MaoScope::view($user) : null;
    $navMaoCanSwitch = $isMaoNav && \App\Support\MaoScope::canSwitch($user);
@endphp

{{-- Top bar of every signed-in page (all roles): single 64px white row, sage-200 bottom border. --}}
<nav class="topbar ic-topbar" aria-label="Top bar">
    <div class="ic-topbar-left">
        <button class="ic-icon-btn ic-topbar-menu-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-expanded="false" aria-label="Open menu">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 6h13M3.5 10h13M3.5 14h13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
        <a class="ic-topbar-brand gov-brand" href="{{ route($user->dashboardRoute()) }}" aria-label="iClimate – Municipal Agriculture Office of Lian, dashboard">
            <img class="gov-mao-logo" src="{{ asset('images/mao-lian-seal.png') }}" alt="Municipal Agriculture Office logo" width="46" height="46">
            <img class="gov-app-logo" src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" width="48" height="38">
            <span class="gov-brand-text">
                <span class="gov-brand-office">Municipal Agriculture Office</span>
                <span class="gov-brand-place">Lian, Batangas</span>
            </span>
        </a>
    </div>

    <div class="ic-topbar-middle">
        <p class="ic-topbar-greeting">{{ $navGreeting }}</p>
    </div>

    <div class="ic-topbar-right">
        @if ($navFarmsOn)
            @if ($navFarms->isNotEmpty())
                <div class="dropdown ic-topbar-farm-wrap" data-farm-select>
                    <button class="ic-farm-btn" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" title="{{ $navFarmLabel }}" aria-label="{{ __('farms.select_label') }}: {{ $navFarmLabel }}">
                        <span class="ic-farm-btn-text">
                            <span class="ic-farm-btn-label">{{ __('farms.select_label') }}</span>
                            <span class="ic-farm-btn-name">{{ $navFarmLabel }}</span>
                        </span>
                        <svg class="ic-farm-chevron" width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end ic-farm-menu">
                        @foreach ($navFarms as $navOption)
                            @php($optLabel = $navOption->farm_name.($navOption->barangay ? ' · '.$navOption->barangay : ''))
                            <form method="POST" action="{{ route('farmer.farms.select') }}">
                                @csrf
                                <input type="hidden" name="farm_id" value="{{ $navOption->id }}">
                                <button type="submit" @class(['dropdown-item ic-farm-option', 'is-selected' => $navOption->id === $navFarm?->id])>
                                    <span class="ic-farm-option-label">{{ $optLabel }}</span>
                                    @if ($navOption->id === $navFarm?->id)
                                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3.5 8.5l3 3 6-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    @endif
                                </button>
                            </form>
                        @endforeach
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item ic-farm-option-add" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add') }}</a>
                    </div>
                </div>
            @else
                <a class="ic-farm-add ic-topbar-farm-wrap" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add_first') }}</a>
            @endif
        @endif

        @if ($navLangRoute)
            <div class="ic-lang ic-topbar-lang" role="group" aria-label="Language">
                @foreach (['en' => 'English', 'fil' => 'Filipino'] as $langCode => $langName)
                    <form method="POST" action="{{ route($navLangRoute) }}">
                        @csrf
                        <input type="hidden" name="language" value="{{ $langCode }}">
                        <button type="submit" lang="{{ $langCode }}" aria-pressed="{{ $navLocale === $langCode ? 'true' : 'false' }}" title="{{ $langName }}" aria-label="{{ $langName }}">@include('layouts.partials.lang-flag', ['code' => $langCode])</button>
                    </form>
                @endforeach
            </div>
        @endif

        @if ($showNavBell)
            <a class="ic-icon-btn position-relative" href="{{ route('notifications.index') }}" data-ic-notifications aria-haspopup="dialog"
                data-feed-url="{{ route('notifications.feed') }}" data-index-url="{{ route('notifications.index') }}" data-mark-all-url="{{ route('notifications.mark-all-read') }}"
                data-notification-user-id="{{ $user->id }}"
                data-fast-nav-bound="true" data-no-loader aria-label="Notifications{{ $navUnread > 0 ? ', '.$navUnread.' unread' : '' }}">
                <svg width="24" height="24" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M5 7.2C5 4.6 6.8 3 9 3s4 1.6 4 4.2c0 3.6 1.3 4.6 1.3 4.6H3.7S5 10.8 5 7.2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 14.5a1.6 1.6 0 0 0 3 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                <span class="sidebar-badge topbar-bell-badge" data-ic-notif-badge @if ($navUnread <= 0) hidden @endif>{{ $navUnread > 99 ? '99+' : $navUnread }}</span>
            </a>
        @endif

        <div class="dropdown">
            <button class="ic-avatar-btn" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false" aria-label="Account menu for {{ $user->name }}">
                @if ($avatarUrl)
                    <img src="{{ $avatarUrl }}" alt="">
                @else
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8.5" r="3.6" fill="currentColor"/><path d="M4.8 20c.9-3.9 3.6-6.1 7.2-6.1s6.3 2.2 7.2 6.1" fill="currentColor"/></svg>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-end ic-account-menu">
                <div class="ic-account-head">
                    <span class="ic-account-avatar" aria-hidden="true">@if ($avatarUrl)<img src="{{ $avatarUrl }}" alt="">@else<svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="8.5" r="3.6" fill="currentColor"/><path d="M4.8 20c.9-3.9 3.6-6.1 7.2-6.1s6.3 2.2 7.2 6.1" fill="currentColor"/></svg>@endif</span>
                    <span class="ic-account-id">
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $isMaoNav ? $user->positionLabel() : $user->roleLabel() }}{{ $navBarangay ? ' · '.$navBarangay : '' }}</span>
                        @if ($isMaoNav)<span class="ic-account-coverage">Covers: {{ $navMaoCoverage }}</span>@endif
                    </span>
                </div>
                <a @class(['dropdown-item', 'active' => request()->routeIs('profile.*')]) href="{{ route('profile.edit') }}">
                    <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="6" r="2.8" stroke="currentColor" stroke-width="1.4"/><path d="M3 15c.7-3.4 2.8-5.2 6-5.2s5.3 1.8 6 5.2" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Profile
                </a>
                <a class="dropdown-item" href="{{ route('profile.edit') }}#password">
                    <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="2.2" stroke="currentColor" stroke-width="1.4"/><path d="M9 2.5v1.6M9 13.9v1.6M2.5 9h1.6M13.9 9h1.6M4.4 4.4l1.1 1.1M12.5 12.5l1.1 1.1M13.6 4.4l-1.1 1.1M5.5 12.5l-1.1 1.1" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    Settings
                </a>
                <div class="dropdown-divider"></div>
                @include('layouts.partials.logout-form', ['variant' => 'menu', 'label' => 'Log out'])
            </div>
        </div>
    </div>
</nav>
@if ($isMaoNav)
    <div @class(['mao-coverage-bar', 'is-municipal' => $navMaoView === \App\Support\MaoScope::VIEW_MUNICIPAL && $navMaoCanSwitch]) data-mao-coverage>
        <span class="mao-coverage-label">
            <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 16s5-4.6 5-8.6A5 5 0 0 0 4 7.4C4 11.4 9 16 9 16Z" stroke="currentColor" stroke-width="1.5"/><circle cx="9" cy="7.4" r="1.8" stroke="currentColor" stroke-width="1.5"/></svg>
            <span><strong>Coverage:</strong> {{ $navMaoCoverage }}</span>
        </span>
        @if ($navMaoCanSwitch)
            <form method="POST" action="{{ route('mao.view.switch') }}" class="mao-view-switch" role="group" aria-label="Data shown">
                @csrf
                <button type="submit" name="view" value="{{ \App\Support\MaoScope::VIEW_ASSIGNED }}" aria-pressed="{{ $navMaoView === \App\Support\MaoScope::VIEW_ASSIGNED ? 'true' : 'false' }}">My barangays</button>
                <button type="submit" name="view" value="{{ \App\Support\MaoScope::VIEW_MUNICIPAL }}" aria-pressed="{{ $navMaoView === \App\Support\MaoScope::VIEW_MUNICIPAL ? 'true' : 'false' }}">Municipal view (read-only)</button>
            </form>
        @endif
    </div>
@endif
