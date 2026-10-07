<x-app-layout>
    {{-- Farmer – Naicel (2026-10-07): the page is called "Dashboard" in both languages (farmer.nav.home), sidebar and browser tab. --}}
    <x-slot:title>{{ __('farmer.nav.home') }}</x-slot:title>
    {{-- Farmer Home (2026-10): five plain parts in reading order, all from saved data (FarmerDashboardService).
         1 Mensahe Ngayon, 2 weather today, 3 barangay risk, 4 expected yield this season, 5 the next 7 days and the
         latest planting advice. The Typhoon Safety Check-In appears only while a typhoon advisory is in effect. --}}
    <div class="mfd" data-farmer-dashboard data-live-url="{{ route('farmer.dashboard.live') }}" lang="{{ $f['locale'] ?? app()->getLocale() }}"
        data-text-refreshing="{{ __('farmer.header.refreshing') }}" data-text-refresh="{{ __('farmer.header.refresh') }}"
        data-text-failed="{{ __('farmer.state.refresh_failed') }}" data-text-language-failed="{{ __('farmer.state.language_failed') }}">

        <div class="mfd-sr-only" role="status" aria-live="polite" data-mfd-live></div>
        <div class="mfd-soft" role="alert" data-mfd-refresh-error hidden><span data-mfd-refresh-error-text>{{ __('farmer.state.refresh_failed') }}</span></div>

        @include('dashboards.farmer.header')

        @foreach (\App\Http\Controllers\DashboardController::FARMER_SLOTS as $name)
            @php $html = view('dashboards.farmer.'.$name, ['f' => $f])->render(); @endphp
            <div class="mfd-slot" data-slot="{{ $name }}" @if (trim($html) === '') hidden @endif>{!! $html !!}</div>
        @endforeach

        {{-- Farmer – Naicel: the two simple charts. Not part of the timed refresh (their numbers change with a save). --}}
        @include('dashboards.farmer.charts')
    </div>
</x-app-layout>
