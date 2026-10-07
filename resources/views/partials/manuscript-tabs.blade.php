{{-- Manuscript – Naicel (2026-10-08): the tab row that joins a manuscript page to the sidebar item it belongs to, so
     the menu stays short. $group picks the tabs; each tab shows only if its route exists and the role may open it.
     A label is a plain string, or [en, fil] for pages farmers see (in the farmer's language). --}}
@php
    $role = auth()->user()?->role;
    $farmerRole = \App\Models\User::ROLE_FARMER;
    $mao = \App\Models\User::ROLE_MAO;
    $tabLocale = $role === $farmerRole ? \App\Support\FarmerLanguage::resolve(request()) : 'en';
    $groups = [
        'farmers' => [
            ['farmer-profiles.index', 'Farmers', ['farmer-profiles.*'], [$mao, \App\Models\User::ROLE_IT_EXPERT]],
            ['mao.farmer-approvals.index', 'Farmer Approvals', ['mao.farmer-approvals.*'], [$mao]],
        ],
        // Records: the two record lists keep their own tab row (crud.index); this one is for the Barangays page.
        'records' => [
            ['climate-records.index', 'Climate Records', ['climate-records.*'], [$mao]],
            ['rice-productions.index', 'Rice Production', ['rice-productions.*'], [$mao]],
            ['mao.barangays.index', 'Barangays', ['mao.barangays.*'], [$mao]],
        ],
        // Predictions (Forecast for farmers) and the analysis pages.
        'analysis' => [
            ['weather-predictions.index', [$role === $farmerRole ? 'Forecast' : 'Predictions', 'Taya ng Panahon'], ['weather-predictions.*'], [$farmerRole, $mao]],
            ['analysis.weather', ['Weather Analysis', 'Pagsusuri ng Panahon'], ['analysis.weather'], [$farmerRole, $mao]],
            ['analysis.yield', ['Yield Prediction', 'Taya ng Ani'], ['analysis.yield*'], [$farmerRole]],
            ['analysis.impact', 'Impact Analysis', ['analysis.impact*'], [$mao]],
            ['analysis.forecast', 'Yield Forecast', ['analysis.forecast*'], [$mao]],
        ],
        // IT: System Settings and the pages next to it.
        'settings' => [
            ['admin.settings.index', 'System Settings', ['admin.settings.index'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.settings.security', 'Security', ['admin.settings.security'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.permissions.index', 'Roles & Permissions', ['admin.permissions.*'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.backups.index', 'Backups', ['admin.backups.*'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.maintenance.index', 'Maintenance Records', ['admin.maintenance.*'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.models.index', 'ML Models', ['admin.models.*'], [\App\Models\User::ROLE_IT_EXPERT]],
            ['admin.feedback.index', 'Model Feedback', ['admin.feedback.*'], [\App\Models\User::ROLE_IT_EXPERT]],   // Farmer Survey – Naicel (2026-10-09)
        ],
    ];
    $tabs = array_values(array_filter($groups[$group] ?? [], fn (array $tab): bool => \Illuminate\Support\Facades\Route::has($tab[0]) && in_array($role, $tab[3], true)));
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
