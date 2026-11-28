@php
    $mobileUser = auth()->user();
    $navFarmsOn = $navFarmsOn ?? ($isFarmer && \App\Support\ActiveFarm::available());
    $navFarm = $navFarm ?? ($navFarmsOn ? \App\Support\ActiveFarm::current($mobileUser) : null);
    $navBarangay = $navBarangay ?? ($isFarmer ? $mobileUser->registeredBarangay() : null);
    $navLocale = $navLocale ?? ($isFarmer ? \App\Support\FarmerLanguage::resolve(request()) : ($isMao ? \App\Support\MaoLanguage::of($mobileUser) : 'en'));
    $navLangRoute = $navLangRoute ?? ($isFarmer ? 'farmer.dashboard.language' : ($isMao ? 'mao.dashboard.language' : null));
    $navUnread = $navUnread ?? 0;
    $mobileLinks = collect([...($primaryGroup ?? []), ...($moreGroup ?? [])]);
    $findMobileLink = function (string $key) use ($mobileLinks): ?array {
        return $mobileLinks->first(fn (array $link): bool => ($link[6] ?? null) === $key);
    };
    $mobileLink = function (string $key, ?string $label = null) use ($findMobileLink): ?array {
        $link = $findMobileLink($key);
        if ($link !== null && $label !== null) {
            $link[0] = $label;
        }
        return $link;
    };
    $mobileTabLinks = $isFarmer
        ? array_values(array_filter([
            $mobileLink('dashboard', $farmerLocale === 'fil' ? 'Ngayon' : 'Today'),
            $mobileLink('heatmap', 'Heat Map'),
            $mobileLink('advisories', __('farmer.nav.advisories', [], $farmerLocale ?? 'en')),
            $mobileLink('my-farm', __('farmer.nav.my_farm', [], $farmerLocale ?? 'en')),
        ]))
        : array_values(array_filter($isMao
            ? [$mobileLink('dashboard'), $mobileLink('heatmap'), $mobileLink('weather'), $mobileLink('advisories')]
            : [$mobileLink('dashboard'), $mobileLink('user-management'), $mobileLink('reports'), $mobileLink('messages')]));
    $mobileBarLabel = function (array $link) use ($isFarmer, $navLocale): string {
        $key = $link[6] ?? '';
        return match ($key) {
            'dashboard' => $isFarmer ? ($navLocale === 'fil' ? 'Ngayon' : 'Today') : 'Home',
            'user-management' => 'Users',
            'reports' => 'Reports',
            'messages' => $isFarmer && $navLocale === 'fil' ? 'Mensahe' : 'Messages',
            'heatmap' => $isFarmer ? __('farmer.nav.heat_map', [], $navLocale ?? 'en') : 'Heat Map',
            'advisories' => $isFarmer ? __('farmer.nav.advisories', [], $navLocale ?? 'en') : 'Advisories',
            'records-and-barangays' => 'Records',
            'my-farm' => $isFarmer && $navLocale === 'fil' ? 'Aking Farm' : 'My Farm',
            'weather' => $navLocale === 'fil' ? 'Panahon' : 'Forecast',
            default => $link[0],
        };
    };

    $mobileMoreGroups = [];
    if ($isFarmer) {
        $mLoc = $farmerLocale ?? $navLocale ?? 'en';
        $mText = fn (string $key): string => __('farmer.mobile_menu.'.$key, [], $mLoc);
        $homeLink = $mobileLink('dashboard', $mText('home'));
        $dailyLinks = array_values(array_filter([
            $homeLink,
            $mobileLink('my-farm'),
            $mobileLink('advisories'),
            $mobileLink('heatmap'),
            $mobileLink('analysis-yield'),
            $mobileLink('rice-varieties'),
            $mobileLink('explore-results'),
        ]));
        $chairmanLink = $mobileLinks->first(fn (array $link): bool => ($link[1] ?? null) === 'association-chairman.dashboard');
        $supportLinks = array_values(array_filter([
            [$mText('talk_to_mao'), 'messages.index', ['messages.*'], 'messages', null, null, 'messages'],
            [$mText('profile'), 'profile.edit', ['profile.*'], 'farmer-profiles', null, null, 'profile'],
            $chairmanLink ? [$chairmanLink[0], $chairmanLink[1], $chairmanLink[2], $chairmanLink[3], null, null, 'association-chairman'] : null,
        ]));
        $mobileMoreGroups = [
            [$mText('group_daily'), $dailyLinks],
            [$mText('group_support'), $supportLinks],
        ];
    } else {
        $mobileMoreGroups = array_map(
            fn (array $section): array => [$section[0], $section[1]],
            $sidebarSections ?? [],
        );
    }

    $mobilePayload = [];
    if ($isFarmer) {
        // The Farmer dashboard already prepared this payload; reuse its cache across all farmer pages instead of rebuilding 20+ queries per page.
        $mobilePayload = request()->attributes->get('farmer_dashboard_payload');
        if (! is_array($mobilePayload)) {
            $mobileCacheKey = \App\Services\Analytics\FarmerDashboardService::cacheKey($user);
            $mobilePayload = \Illuminate\Support\Facades\Cache::get($mobileCacheKey);
            if (! is_array($mobilePayload)) {
                $mobilePayload = rescue(fn (): array => app(\App\Services\Analytics\FarmerDashboardService::class)->payload($user), [], false);
                if ($mobilePayload !== []) {
                    \Illuminate\Support\Facades\Cache::put($mobileCacheKey, $mobilePayload, now()->addMinutes(5));
                }
            }
        }
    }
    $mobileCrop = collect(data_get($mobilePayload, 'kpis', []))->firstWhere('key', 'crop');
    $mobileFarmName = data_get($mobilePayload, 'farm.name') ?: ($navFarm?->farm_name ?? null);
    $mobileBarangay = data_get($mobilePayload, 'barangay') ?: ($navFarm?->barangay ?? $navBarangay ?? null);
    $mobileWeather = data_get($mobilePayload, 'weather', []);
    $mobileWeatherValue = data_get($mobileWeather, 'temperature');
    $mobileRain = data_get($mobileWeather, 'rain') ?: data_get($mobileWeather, 'condition');
    $mobileContextVisible = $isFarmer && $mobileFarmName && ($mobileWeatherValue !== null || $mobileCrop !== null);
    $mobileContextText = $mobileContextVisible
        ? $mobileFarmName.' · '.($mobileWeatherValue !== null ? number_format((float) $mobileWeatherValue, 0).'°C' : $mobileBarangay).
            ($mobileRain ? ' '.$mobileRain : '').($mobileCrop ? ' · '.$mobileCrop['value'].' '.($mobileCrop['unit'] ?? __('farmer.kpis.days', [], $farmerLocale ?? 'en')).' to harvest' : '')
        : null;
    $mobileProgress = $mobileCrop['progress'] ?? null;
    $mobileUnread = (int) ($navCounts['unread'] ?? $navUnread ?? 0);
    $mobileAdvisoryCount = $isFarmer ? (int) ($navCounts['unread_advisories'] ?? 0) : 0;
    $mobilePendingCount = $isMao ? (int) ($navPendingCount ?? 0) : 0;
    $mobileBadge = function (string $key) use ($mobileUnread, $mobileAdvisoryCount, $mobilePendingCount): ?int {
        return match ($key) {
            'messages' => (int) ($mobileUnread ?? 0),
            'advisories' => (int) ($mobileAdvisoryCount ?? 0),
            'farmer-profiles-and-approvals' => (int) ($mobilePendingCount ?? 0),
            default => null,
        };
    };
    $formatMobileBadge = function (string $key, int $count): string {
        if ($key === 'advisories') {
            return $count > 9 ? '9+' : (string) $count;
        }

        return $count > 99 ? '99+' : (string) $count;
    };
    $mobileIcon = $sidebarIcon ?? fn (): string => '';
    $mobileActive = fn (array $link): bool => request()->routeIs(...($link[2] ?? []));
@endphp

<nav class="mobile-nav-shell" data-mobile-nav aria-label="{{ __('farmer.topbar.mobile_nav') }}">
    <div class="mobile-season-line {{ $mobileProgress === null ? 'is-plain' : 'is-progress' }}" data-mobile-season-line>
        <span style="width: {{ $mobileProgress !== null ? max(0, min(100, (int) $mobileProgress)) : 0 }}%"></span>
    </div>
    @if ($mobileContextVisible)
        <a class="mobile-context-strip" href="{{ route('farmer.dashboard') }}" data-mobile-context>{{ $mobileContextText }}</a>
    @endif
    <div class="mobile-tabs">
        @foreach ($mobileTabLinks as $link)
            @php($linkKey = $link[6] ?? '')
            <a class="mobile-tab {{ $mobileActive($link) ? 'is-active' : '' }}" href="{{ $link[5] ? route($link[1]).'#'.$link[5] : route($link[1]) }}" @if ($mobileActive($link)) aria-current="page" @endif>
                <span class="mobile-tab-icon" aria-hidden="true">{!! $mobileIcon($link[3]) !!}</span>
                <span class="mobile-tab-label">{{ $mobileBarLabel($link) }}</span>
                @if (($count = $mobileBadge($linkKey)) > 0)<span class="mobile-tab-badge">{{ $formatMobileBadge($linkKey, $count) }}</span>@endif
            </a>
        @endforeach
        <button class="mobile-tab mobile-more-trigger" type="button" data-mobile-more-open aria-expanded="false" aria-controls="mobileMoreSheet">
            <span class="mobile-tab-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 20 20" fill="none"><rect x="3" y="3" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11" y="3" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="3" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/><rect x="11" y="11" width="6" height="6" rx="1.5" stroke="currentColor" stroke-width="1.6"/></svg>
            </span>
            <span class="mobile-tab-label">{{ $isFarmer ? __('farmer.mobile_menu.more', [], $farmerLocale ?? $navLocale ?? 'en') : 'More' }}</span>
            <span class="mobile-tab-badge" data-ic-notif-badge @if ($mobileUnread <= 0) hidden @endif>{{ $mobileUnread > 99 ? '99+' : $mobileUnread }}</span>
        </button>
    </div>
</nav>

<div class="mobile-more-layer" data-mobile-more hidden>
    <div class="mobile-more-backdrop" data-mobile-more-close></div>
    <section class="mobile-more-sheet" id="mobileMoreSheet" role="dialog" aria-modal="true" aria-labelledby="mobileMoreTitle" tabindex="-1">
        <div class="mobile-more-head">
            <div class="mobile-more-handle" aria-hidden="true"></div>
            <div class="mobile-more-head-row">
                <div class="d-flex align-items-center gap-2 min-w-0">
                    <x-user-avatar :user="$mobileUser" :size="40" />
                    <div class="min-w-0">
                        <h2 id="mobileMoreTitle" class="mb-0 text-truncate">{{ $mobileUser->name }}</h2>
                        <div class="mobile-more-role text-truncate">{{ $isFarmer ? __('farmer.mobile_menu.role_farmer', [], $farmerLocale ?? $navLocale ?? 'en').($mobileBarangay ? ' · '.$mobileBarangay : '') : ($mobileUser->isMao() ? $mobileUser->positionLabel() : $mobileUser->roleLabel()) }}</div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 flex-shrink-0">
                    @if ($navLangRoute)
                        <div class="mobile-more-language" role="group" aria-label="{{ __('farmer.topbar.language', [], $navLocale ?? 'en') }}">
                            @foreach (['en' => 'English', 'fil' => 'Filipino'] as $langCode => $langName)
                                <form method="POST" action="{{ route($navLangRoute) }}">
                                    @csrf
                                    <input type="hidden" name="language" value="{{ $langCode }}">
                                    <button type="submit" lang="{{ $langCode }}" aria-pressed="{{ ($navLocale ?? 'en') === $langCode ? 'true' : 'false' }}" title="{{ $langName }}" aria-label="{{ $langName }}">@include('layouts.partials.lang-flag', ['code' => $langCode])</button>
                                </form>
                            @endforeach
                        </div>
                    @endif
                    <button type="button" class="mobile-more-close" data-mobile-more-close aria-label="{{ __('farmer.mobile_menu.close_menu', [], $farmerLocale ?? $navLocale ?? 'en') }}">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true"><path d="M5 5l10 10M15 5 5 15" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="mobile-more-scroll">
            @foreach ($mobileMoreGroups as [$groupLabel, $links])
                @if ($links !== [])
                    <section class="mobile-more-group" aria-labelledby="mobile-more-group-{{ $loop->index }}">
                        <h3 id="mobile-more-group-{{ $loop->index }}">{{ $groupLabel }}</h3>
                        @foreach ($links as $link)
                            @php($linkKey = $link[6] ?? '')
                            @php($active = $mobileActive($link))
                            @php($descLocale = $farmerLocale ?? $navLocale ?? 'en')
                            @php($rowDesc = match ($linkKey) {
                                'dashboard' => __('farmer.mobile_menu.home_desc', [], $descLocale),
                                'my-farm' => __('farmer.mobile_menu.my_farm_desc', [], $descLocale),
                                'advisories' => __('farmer.mobile_menu.advisories_desc', [], $descLocale),
                                'heatmap' => __('farmer.mobile_menu.heatmap_desc', [], $descLocale),
                                'analysis-yield' => __('farmer.mobile_menu.yield_prediction_desc', [], $descLocale),
                                'rice-varieties' => __('farmer.mobile_menu.rice_varieties_desc', [], $descLocale),
                                'explore-results' => __('farmer.mobile_menu.explore_results_desc', [], $descLocale),
                                'messages' => __('farmer.mobile_menu.talk_to_mao_desc', [], $descLocale),
                                'profile' => __('farmer.mobile_menu.profile_desc', [], $descLocale),
                                'association-chairman' => __('farmer.mobile_menu.association_reports_desc', [], $descLocale),
                                default => __('farmer.mobile_menu.open_section', [], $descLocale),
                            })
                            <a class="mobile-more-row {{ $active ? 'is-active' : '' }}" href="{{ $link[5] ? route($link[1]).'#'.$link[5] : route($link[1]) }}" @if ($active) aria-current="page" @endif>
                                <span class="mobile-more-row-icon" aria-hidden="true">{!! $mobileIcon($link[3]) !!}</span>
                                <span class="mobile-more-row-body"><strong>{{ $link[0] }}</strong><small>{{ $rowDesc }}</small></span>
                                @if (($count = $mobileBadge($linkKey)) > 0)<em>{{ $formatMobileBadge($linkKey, $count) }}</em>@endif
                                <svg class="mobile-more-row-chevron" width="16" height="16" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="m6 3.5 4.5 4.5L6 12.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </a>
                        @endforeach
                    </section>
                @endif
            @endforeach
            <div class="mobile-more-footer">
                @if ($isFarmer && $navFarmsOn)
                    @if (($navFarms ?? collect())->count() > 1)
                        <form method="POST" action="{{ route('farmer.farms.select') }}" class="mobile-more-farm-form">
                            @csrf
                            <label class="visually-hidden" for="mobileMoreFarm">{{ __('farms.select_label') }}</label>
                            <select id="mobileMoreFarm" name="farm_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                @foreach ($navFarms as $mobileOpt)
                                    <option value="{{ $mobileOpt->id }}" @selected($mobileOpt->id === ($navFarm?->id))>{{ $mobileOpt->farm_name }}{{ $mobileOpt->barangay ? ' · '.$mobileOpt->barangay : '' }}</option>
                                @endforeach
                            </select>
                        </form>
                    @else
                        <span class="mobile-more-farm">{{ $mobileFarmName ?: __('farms.add_first') }}</span>
                    @endif
                @endif
                @if ($navLangRoute)
                    <div class="mobile-more-language" role="group" aria-label="{{ __('farmer.topbar.language') }}">
                        @foreach (['en' => 'English', 'fil' => 'Filipino'] as $mobileLangCode => $mobileLangLabel)
                            <form method="POST" action="{{ route($navLangRoute) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="language" value="{{ $mobileLangCode }}"><button type="submit" lang="{{ $mobileLangCode }}" aria-pressed="{{ $navLocale === $mobileLangCode ? 'true' : 'false' }}" title="{{ $mobileLangLabel }}" aria-label="{{ $mobileLangLabel }}">@include('layouts.partials.lang-flag', ['code' => $mobileLangCode])</button></form>
                        @endforeach
                    </div>
                @endif
                @include('layouts.partials.logout-form', ['variant' => $isFarmer ? 'sidebar' : 'menu', 'label' => $isFarmer ? __('farmer.nav.logout', [], $farmerLocale ?? 'en') : 'Log out'])
            </div>
        </div>
    </section>
</div>
