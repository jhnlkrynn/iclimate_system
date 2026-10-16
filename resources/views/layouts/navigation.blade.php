@php
    $user = auth()->user();
    $avatarUrl = $user->avatarUrl();
    $isItNav = $user->role === \App\Models\User::ROLE_IT_EXPERT;
    $isFarmerNav = $user->role === \App\Models\User::ROLE_FARMER;
    // English / Filipino for the roles that have it (farmers and MAO); IT works in English.
    $navLangRoute = $isFarmerNav ? 'farmer.dashboard.language' : ($user->role === \App\Models\User::ROLE_MAO ? 'mao.dashboard.language' : null);
    $navLocale = $isFarmerNav ? \App\Support\FarmerLanguage::resolve(request()) : ($navLangRoute ? \App\Support\MaoLanguage::of($user) : null);
    $navBarangay = $isFarmerNav ? ($user->farmerProfile?->barangay ?? $user->barangay) : null;
    // "Good afternoon, Naicel!" by Manila time, in the account's language (the farmer.greeting lines).
    $navHour = now('Asia/Manila')->hour;
    $navPeriod = $navHour >= 5 && $navHour < 12 ? 'morning' : ($navHour >= 12 && $navHour < 18 ? 'afternoon' : 'evening');
    $navGreeting = __('farmer.greeting.'.$navPeriod, ['name' => $user->greetingName()], $navLocale ?? 'en');
    // Every role gets the same bell: it opens their own notifications in the shared pop-up (resources/js/notifications-popup.js).
    $showNavBell = true;
    $navBarangay = $isFarmerNav ? $user->registeredBarangay() : null;
    // The My Farm selector: the farmer's farms (read once per request) and the one their pages show.
    $navFarmsOn = $isFarmerNav && \App\Support\ActiveFarm::available();
    $navFarms = $navFarmsOn ? \App\Support\ActiveFarm::farms($user) : collect();
    $navFarm = $navFarmsOn ? \App\Support\ActiveFarm::current($user) : null;
    // The bell is the only entry point to notifications (the sidebar has no Notifications item); the count is cached briefly.
    $navUnread = $showNavBell
        ? \Illuminate\Support\Facades\Cache::remember('sidebar:unread-notifications:'.$user->id, now()->addSeconds(20), fn () => \App\Support\SampleData::without(\App\Models\Notification::query(), 'notifications')->where('user_id', $user->id)->where('is_read', false)->count())
        : 0;
    // Panel revision 4.4 C: the signed-in MAO user by name and position, with the barangays they cover.
    $isMaoNav = $user->isMao();
    $navMaoCoverage = $isMaoNav ? $user->coverageLabel() : null;
    $navMaoView = $isMaoNav ? \App\Support\MaoScope::view($user) : null;
    $navMaoCanSwitch = $isMaoNav && \App\Support\MaoScope::canSwitch($user);
@endphp

{{-- Panel revision 4.5: the government strip above the top bar (Republic of the Philippines · Municipality of Lian). --}}
<div class="gov-strip" role="note">
    <span>Republic of the Philippines</span>
    <span class="gov-strip-sep" aria-hidden="true">·</span>
    <span>Province of Batangas</span>
    <span class="gov-strip-sep" aria-hidden="true">·</span>
    <strong>Municipality of Lian</strong>
</div>

{{-- Top bar of every signed-in page (styles: resources/css/app-shell.css). Left: the menu button, which opens the
     navigation drawer (layouts/sidebar.blade.php), and the logo. Right: the farmer's farm, notifications, the
     light/dark switch and the account menu (profile photo or initials). --}}
<nav class="topbar ic-topbar" aria-label="Top bar">
    <div class="ic-topbar-left">
        <button class="ic-icon-btn" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar" aria-expanded="false" aria-label="Open menu">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M3.5 6h13M3.5 10h13M3.5 14h13" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
        </button>
        {{-- Panel revision 4.5: the office's name and the official seal of the Municipality of Lian (public/images/lian-seal.jpg). --}}
        <a class="ic-topbar-brand gov-brand" href="{{ route($user->dashboardRoute()) }}" aria-label="iClimate – Municipal Agriculture Office of Lian, dashboard">
            <img class="gov-seal" src="{{ asset('images/lian-seal.jpg') }}" alt="Seal of the Municipality of Lian" width="44" height="44">
            {{-- Farmer accounts: the iClimate logo beside the seal, as on the landing page (resources/css/pages/farmer-account.css). --}}
            @if ($isFarmerNav)
                <img class="gov-app-logo" src="{{ asset('images/iclimate-logo.png') }}" alt="iClimate" width="58" height="47">
            @endif
            <span class="gov-brand-text">
                <span class="gov-brand-office">Municipal Agriculture Office</span>
                <span class="gov-brand-place">Municipality of Lian, Batangas · iClimate</span>
            </span>
        </a>
        @unless ($isMaoNav)
            <p class="ic-topbar-greeting">{{ $navGreeting }}</p>
        @endunless
    </div>

    @if ($isMaoNav)
        {{-- Panel revision 4.4 C: who is signed in, e.g. "Jean L. Laviste – Agricultural Technologist". --}}
        <div class="mao-identity" data-mao-identity>
            <strong class="mao-identity-name">{{ $user->name }}</strong>
            <span class="mao-identity-position">{{ $user->positionLabel() }}</span>
        </div>
    @endif

    <div class="ic-topbar-right">
        @if ($navFarmsOn)
            @if ($navFarms->isNotEmpty())
                <form class="topbar-farm" method="POST" action="{{ route('farmer.farms.select') }}" data-farm-select>
                    @csrf
                    <label for="topbarFarm">{{ __('farms.select_label') }}</label>
                    <select id="topbarFarm" name="farm_id" data-add-url="{{ route('farmer.farms.create') }}" aria-describedby="topbarFarmNote">
                        @foreach ($navFarms as $navOption)
                            <option value="{{ $navOption->id }}" @selected($navOption->id === $navFarm?->id)>{{ $navOption->farm_name }}{{ $navOption->barangay ? ' · '.$navOption->barangay : '' }}</option>
                        @endforeach
                        <option value="add">+ {{ __('farms.add') }}</option>
                    </select>
                    <span id="topbarFarmNote" class="visually-hidden">{{ __('farms.current') }}</span>
                    <noscript><button type="submit" class="topbar-btn">{{ __('farms.view') }}</button></noscript>
                </form>
            @else
                <a class="topbar-farm topbar-farm-add" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add_first') }}</a>
            @endif
        @endif
            @if ($showNavBell)
                <a class="ic-icon-btn position-relative" href="{{ route('notifications.index') }}" data-ic-notifications aria-haspopup="dialog"
                    data-feed-url="{{ route('notifications.feed') }}" data-index-url="{{ route('notifications.index') }}" data-mark-all-url="{{ route('notifications.mark-all-read') }}"
                    data-fast-nav-bound="true" data-no-loader aria-label="Notifications{{ $navUnread > 0 ? ', '.$navUnread.' unread' : '' }}">
                    <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M5 7.2C5 4.6 6.8 3 9 3s4 1.6 4 4.2c0 3.6 1.3 4.6 1.3 4.6H3.7S5 10.8 5 7.2Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M7.5 14.5a1.6 1.6 0 0 0 3 0" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                    <span class="sidebar-badge topbar-bell-badge" data-ic-notif-badge @if ($navUnread <= 0) hidden @endif>{{ $navUnread > 99 ? '99+' : $navUnread }}</span>
                </a>
            @endif

            {{-- Language (farmers and MAO), in its own box. The app has one appearance: dark. --}}
            @if ($navLangRoute)
            <div class="ic-topbar-group" role="group" aria-label="Language settings">
                {{-- One form per language with a hidden input, so the choice is sent even if a script disables the clicked button. --}}
                <div class="ic-lang" role="group" aria-label="Language">
                    @foreach (['en' => ['EN', 'English'], 'fil' => ['FIL', 'Filipino']] as $langCode => [$langShort, $langName])
                        <form method="POST" action="{{ route($navLangRoute) }}">
                            @csrf
                            <input type="hidden" name="language" value="{{ $langCode }}">
                            <button type="submit" lang="{{ $langCode }}" aria-pressed="{{ $navLocale === $langCode ? 'true' : 'false' }}" title="{{ $langName }}">{{ $langShort }}</button>
                        </form>
                    @endforeach
                </div>
            </div>
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
                        Profile, photo and password
                    </a>
                    @if ($isItNav)
                        <a @class(['dropdown-item', 'active' => request()->routeIs('admin.settings.index')]) href="{{ route('admin.settings.index') }}">
                            <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="2.4" stroke="currentColor" stroke-width="1.4"/><path d="M9 1.8v2M9 14.2v2M1.8 9h2M14.2 9h2M3.9 3.9l1.4 1.4M12.7 12.7l1.4 1.4M3.9 14.1l1.4-1.4M12.7 5.3l1.4-1.4" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/></svg>
                            System settings
                        </a>
                        <a @class(['dropdown-item', 'active' => request()->routeIs('admin.settings.security')]) href="{{ route('admin.settings.security') }}">
                            <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M9 1.8l5.6 2v4.1c0 3.7-2.4 6.3-5.6 7.3-3.2-1-5.6-3.6-5.6-7.3V3.8L9 1.8Z" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/></svg>
                            Security
                        </a>
                    @endif
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}" data-logout-confirm="Are you sure you want to log out?" data-loading="true">
                        @csrf
                        <button class="dropdown-item ic-account-logout" type="submit" data-loading-text="Logging out...">
                            <svg width="16" height="16" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M7 2.5H4a1.5 1.5 0 0 0-1.5 1.5v10A1.5 1.5 0 0 0 4 15.5h3" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/><path d="M11 5.5l3.5 3.5-3.5 3.5M14.3 9H6.5" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            Log out
                        </button>
                    </form>
                </div>
            </div>
    </div>
</nav>
@if ($isMaoNav)
    {{-- Panel revision 4.4 B/C: the barangays this MAO user covers, and the switch between them and the municipal view. --}}
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
@if ($navFarmsOn)
    <script>
        // My Farm selector: choosing a farm shows that farm's pages; "+ Add Farm" opens the form.
        document.querySelector('[data-farm-select] select')?.addEventListener('change', (event) => {
            const select = event.target;
            if (select.value === 'add') { window.location.href = select.dataset.addUrl; return; }
            select.form.requestSubmit ? select.form.requestSubmit() : select.form.submit();
        });
    </script>
@endif
