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
            $mobileLink('weather', __('farmer.nav.forecast', [], $farmerLocale ?? 'en')),
            $mobileLink('advisories', __('farmer.nav.advisories', [], $farmerLocale ?? 'en')),
            $mobileLink('my-farm', __('farmer.nav.my_farm', [], $farmerLocale ?? 'en')),
        ]))
        : array_values(array_filter($isMao
            ? [$mobileLink('dashboard'), $mobileLink('heatmap'), $mobileLink('advisories'), $mobileLink('records-and-barangays')]
            : [$mobileLink('dashboard'), $mobileLink('user-management'), $mobileLink('reports'), $mobileLink('messages')]));
    $mobileBarLabel = function (array $link) use ($isFarmer, $navLocale): string {
        $key = $link[6] ?? '';
        return match ($key) {
            'dashboard' => $isFarmer ? ($navLocale === 'fil' ? 'Ngayon' : 'Today') : 'Home',
            'user-management' => 'Users',
            'reports' => 'Reports',
            'messages' => $isFarmer && $navLocale === 'fil' ? 'Mensahe' : 'Messages',
            'heatmap' => 'Heat Map',
            'advisories' => $isFarmer && $navLocale === 'fil' ? 'Advice' : 'Advisories',
            'records-and-barangays' => 'Records',
            'my-farm' => $isFarmer && $navLocale === 'fil' ? 'Aking Farm' : 'My Farm',
            'weather' => 'Forecast',
            default => $link[0],
        };
    };

    $mobileMoreGroups = [];
    if ($isFarmer) {
        $mobileMoreGroups = [
            ['My farm', array_values(array_filter([$mobileLink('my-farm'), $mobileLink('rice-varieties')]))],
            ['Weather & risk', array_values(array_filter([$mobileLink('weather'), $mobileLink('heatmap'), $mobileLink('advisories')]))],
            ['Talk to the MAO', array_values(array_filter([
                $mobileLink('messages'),
                [($farmerLocale ?? 'en') === 'fil' ? 'Impormasyon sa tanggapan' : 'Contact info', $dashboardRoute, [$dashboardRoute], 'community', null, 'farmerMaoContact', 'contact-info'],
            ]))],
        ];
    } else {
        $mobileMoreGroups = array_map(
            fn (array $section): array => [$section[0], $section[1]],
            $sidebarSections ?? [],
        );
    }

    $mobilePayload = [];
    if ($isFarmer) {
        $mobilePayload = rescue(fn (): array => app(\App\Services\Analytics\FarmerDashboardService::class)->payload($user), [], false);
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
    $mobileUnread = (int) ($navUnread ?? 0);
    $mobileAdvisoryCount = $isFarmer ? rescue(fn (): int => \App\Models\PlantingAdvisory::query()->published()->count(), 0, false) : 0;
    $mobilePendingCount = ($isMao || $isIt) ? rescue(fn (): int => \App\Models\User::query()->where('status', \App\Models\User::STATUS_PENDING)->count(), 0, false) : 0;
    $mobileBadge = function (string $key) use ($mobileUnread, $mobileAdvisoryCount, $mobilePendingCount): ?int {
        return match ($key) {
            'messages' => (int) ($mobileUnread ?? 0),
            'advisories' => (int) ($mobileAdvisoryCount ?? 0),
            'farmer-profiles-and-approvals' => (int) ($mobilePendingCount ?? 0),
            default => null,
        };
    };
    $mobileIcon = $sidebarIcon ?? fn (): string => '';
    $mobileActive = fn (array $link): bool => request()->routeIs(...($link[2] ?? []));
@endphp

<nav class="mobile-nav-shell" data-mobile-nav aria-label="Mobile navigation">
    <div class="mobile-season-line {{ $mobileProgress === null ? 'is-plain' : 'is-progress' }}" data-mobile-season-line>
        <span style="width: {{ $mobileProgress !== null ? max(0, min(100, (int) $mobileProgress)) : 0 }}%"></span>
    </div>
    @if ($mobileContextVisible)
        <a class="mobile-context-strip" href="{{ route('weather-predictions.index') }}" data-mobile-context>{{ $mobileContextText }}</a>
    @endif
    <div class="mobile-tabs">
        @foreach ($mobileTabLinks as $link)
            @php($linkKey = $link[6] ?? '')
            <a class="mobile-tab {{ $mobileActive($link) ? 'is-active' : '' }}" href="{{ $link[5] ? route($link[1]).'#'.$link[5] : route($link[1]) }}" @if ($mobileActive($link)) aria-current="page" @endif>
                <span class="mobile-tab-icon" aria-hidden="true">{!! $mobileIcon($link[3]) !!}</span>
                <span class="mobile-tab-label">{{ $mobileBarLabel($link) }}</span>
                @if (($count = $mobileBadge($linkKey)) > 0)<span class="mobile-tab-badge">{{ $count > 99 ? '99+' : $count }}</span>@endif
            </a>
        @endforeach
        <button class="mobile-tab mobile-more-trigger" type="button" data-mobile-more-open aria-expanded="false" aria-controls="mobileMoreSheet">
            <span class="mobile-tab-icon" aria-hidden="true">{!! $mobileIcon('system-logs') !!}</span>
            <span class="mobile-tab-label">{{ $isFarmer && ($navLocale ?? 'en') === 'fil' ? 'Higit pa' : 'More' }}</span>
            @if ($mobileUnread > 0)<span class="mobile-tab-badge">{{ $mobileUnread > 99 ? '99+' : $mobileUnread }}</span>@endif
        </button>
    </div>
</nav>

<div class="mobile-more-layer" data-mobile-more hidden>
    <div class="mobile-more-backdrop" data-mobile-more-close></div>
    <section class="mobile-more-sheet" id="mobileMoreSheet" role="dialog" aria-modal="true" aria-labelledby="mobileMoreTitle" tabindex="-1">
        <div class="mobile-more-handle" aria-hidden="true"></div>
        <div class="mobile-more-head"><h2 id="mobileMoreTitle">{{ $isFarmer && ($navLocale ?? 'en') === 'fil' ? 'Higit pa' : 'More' }}</h2><button type="button" class="mobile-more-close" data-mobile-more-close>Close</button></div>
        <div class="mobile-more-scroll">
            @foreach ($mobileMoreGroups as [$groupLabel, $links])
                @if ($links !== [])
                    <section class="mobile-more-group" aria-labelledby="mobile-more-group-{{ $loop->index }}">
                        <h3 id="mobile-more-group-{{ $loop->index }}">{{ $groupLabel }}</h3>
                        @foreach ($links as $link)
                            @php($linkKey = $link[6] ?? '')
                            @php($active = $mobileActive($link))
                            <a class="mobile-more-row {{ $active ? 'is-active' : '' }}" href="{{ $link[5] ? route($link[1]).'#'.$link[5] : route($link[1]) }}" @if ($active) aria-current="page" @endif>
                                <span><strong>{{ $link[0] }}</strong><small>{{ match ($linkKey) {
                                    'heatmap' => 'Flood, drought and heat risk by barangay',
                                    'weather' => 'Rain, heat and typhoon outlook for your barangay',
                                    'advisories' => 'Planting guidance and current MAO notices',
                                    'messages' => 'Messages from the MAO and iClimate team',
                                    'rice-varieties' => 'Browse the rice varieties in the system',
                                    'my-farm' => 'Farm details, planting and harvest records',
                                    'contact-info' => 'Assigned MAO contact information',
                                    default => 'Open this section',
                                } }}</small></span>
                                @if (($count = $mobileBadge($linkKey)) > 0)<em>{{ $count > 99 ? '99+' : $count }}</em>@endif
                            </a>
                        @endforeach
                    </section>
                @endif
            @endforeach
            <div class="mobile-more-footer">
                @if ($isFarmer && $navFarmsOn)<span class="mobile-more-farm">{{ $mobileFarmName ?: __('farms.add_first') }}</span>@endif
                @if ($navLangRoute)
                    <div class="mobile-more-language" aria-label="Language">
                        @foreach (['en' => 'EN', 'fil' => 'FIL'] as $mobileLangCode => $mobileLangLabel)
                            <form method="POST" action="{{ route($navLangRoute) }}"><input type="hidden" name="_token" value="{{ csrf_token() }}"><input type="hidden" name="language" value="{{ $mobileLangCode }}"><button type="submit">{{ $mobileLangLabel }}</button></form>
                        @endforeach
                    </div>
                @endif
                @include('layouts.partials.logout-form', ['variant' => $isFarmer ? 'sidebar' : 'menu', 'label' => $isFarmer ? __('farmer.nav.logout', [], $farmerLocale ?? 'en') : 'Log out'])
            </div>
        </div>
    </section>
</div>
