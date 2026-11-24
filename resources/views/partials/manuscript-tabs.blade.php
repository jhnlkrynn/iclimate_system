{{-- Manuscript – Naicel (2026-10-08): the tab row that joins a manuscript page to the sidebar item it belongs to, so
     the menu stays short. $group picks the tabs; each tab shows only if its route exists and the role may open it.
     A label is a plain string, or [en, fil] for pages farmers see (in the farmer's language). --}}
@php
    $role = auth()->user()?->role;
    $farmerRole = \App\Models\User::ROLE_FARMER;
    $mao = \App\Models\User::ROLE_MAO;
    $tabLocale = $role === $farmerRole ? \App\Support\FarmerLanguage::resolve(request()) : 'en';
    $groups = [
        'advisories-and-planner' => [
            ['planting-advisories.index', ['Advisories', 'Mga Advisory'], ['planting-advisories.*', 'management.advisories.*', 'mao.alerts.*'], [$mao]],
            ['mao.planner.index', ['MAO Planner', 'Planner ng MAO'], ['mao.planner.*', 'calendar.*'], [$mao]],
        ],
        'farmers' => [
            ['farmer-profiles.index', 'Farmers', ['farmer-profiles.*'], [$mao]],
            ['mao.farmer-approvals.index', 'Account Requests', ['mao.farmer-approvals.*'], [$mao]],
            ['mao.farm-verification.index', 'Farm Verification', ['mao.farm-verification.*'], [$mao]],
            ['mao.association-reports.index', 'Association Reports', ['mao.association-reports.*'], [$mao]],
            ['mao.farm-associations.index', 'Association Requests', ['mao.farm-associations.index'], [$mao]],
        ],
        // Records: the two record lists keep their own tab row (crud.index); this one is for the Barangays page.
        'records' => [
            ['climate-records.index', 'Climate Records', ['climate-records.*'], [$mao]],
            ['rice-productions.index', 'Rice Production', ['rice-productions.*'], [$mao]],
            ['mao.barangays.index', 'Barangays', ['mao.barangays.*'], [$mao]],
            ['reports.index', 'Reports', ['reports.*'], [$mao]],
        ],
        // Panel revision 4.4: the MAO Office pages.
        'mao-office' => [
            ['mao.activity-log.index', 'Activity Log', ['mao.activity-log.*'], [$mao]],
            ['mao.personnel.index', 'MAO Personnel', ['mao.personnel.*'], [$mao]],
        ],
        // Predictions (Forecast for MAO) and Weather Analysis.
        'analysis' => [
            ['weather-predictions.index', ['Forecast', 'Taya ng Panahon'], ['weather-predictions.*'], [$mao]],
            ['analysis.weather', ['Weather Analysis', 'Pagsusuri ng Panahon'], ['analysis.weather'], [$farmerRole, $mao]],
            ['analysis.yield', ['Yield Prediction', 'Taya ng Ani'], ['analysis.yield*'], [$farmerRole]],
        ],
    ];
    $groupKey = (string) ($group ?? '');
    if ($groupKey === 'approvals') {
        $groupKey = 'farmers';
    }
    $tabs = array_values(array_filter($groups[$groupKey] ?? [], fn (array $tab): bool => \Illuminate\Support\Facades\Route::has($tab[0]) && in_array($role, $tab[3], true)));
@endphp
@if (count($tabs) > 1)
    <nav class="ic-tabs mb-4" aria-label="{{ $label ?? 'Pages' }}" data-manuscript-tabs="{{ $group }}">
        @foreach ($tabs as [$tabRoute, $tabLabel, $patterns])
            @php
                $active = request()->routeIs(...$patterns);
                $text = is_array($tabLabel) ? ($tabLocale === 'fil' ? $tabLabel[1] : $tabLabel[0]) : $tabLabel;
            @endphp
            <a href="{{ route($tabRoute) }}" @class(['ic-tab', 'is-active' => $active]) @if ($active) aria-current="page" @endif>{{ $text }}</a>
        @endforeach
    </nav>
@endif
