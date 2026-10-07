{{-- Manuscript – Naicel (2026-10-08): the tab row that joins a manuscript page to the sidebar item it belongs to, so
     the menu stays short. $group picks the tabs; each tab shows only if its route exists and the role may open it. --}}
@php
    $role = auth()->user()?->role;
    $groups = [
        'farmers' => [
            ['farmer-profiles.index', 'Farmers', ['farmer-profiles.*'], [\App\Models\User::ROLE_MAO, \App\Models\User::ROLE_IT_EXPERT]],
            ['mao.farmer-approvals.index', 'Farmer Approvals', ['mao.farmer-approvals.*'], [\App\Models\User::ROLE_MAO]],
        ],
    ];
    $tabs = array_values(array_filter($groups[$group] ?? [], fn (array $tab): bool => \Illuminate\Support\Facades\Route::has($tab[0]) && in_array($role, $tab[3], true)));
@endphp
@if (count($tabs) > 1)
    <nav class="ic-tabs mb-4" aria-label="{{ $label ?? 'Pages' }}" data-manuscript-tabs="{{ $group }}">
        @foreach ($tabs as [$tabRoute, $tabLabel, $patterns])
            @php $active = request()->routeIs(...$patterns); @endphp
            <a href="{{ route($tabRoute) }}" @class(['ic-tab', 'is-active' => $active]) @if ($active) aria-current="page" @endif>{{ $tabLabel }}</a>
        @endforeach
    </nav>
@endif
