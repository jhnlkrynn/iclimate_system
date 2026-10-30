<x-app-layout>
    <x-slot:title>{{ __('my_reports.title') }}</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 7: FarmerReportController::index. Print uses the browser; the PDF is
         made by FarmerReportController::pdf from the same body. --}}
    <style>
        .mr-section { margin-top: 1rem; }
        .mr-section h2 { font-size: 1.15rem; font-weight: 800; }
        .mr-section h3 { font-size: .95rem; font-weight: 700; margin-top: .9rem; }
        .mr-table { width: 100%; border-collapse: collapse; font-size: .92rem; }
        .mr-table th, .mr-table td { padding: .35rem .5rem; border-bottom: 1px solid var(--line, var(--sage-100)); text-align: left; vertical-align: top; }
        .mr-print-head { display: none; }
        @media print { .mr-no-print, .sidebar, header.app-topbar { display: none !important; } .mr-print-head { display: block; } }
    </style>
    <div class="fad" data-my-reports>
        <div class="mr-print-head">
            <strong>{{ $office }}</strong> · {{ __('my_reports.for', ['name' => $user->name]) }} · {{ __('my_reports.printed', ['date' => $printedAt->format('M j, Y g:i A')]) }}
        </div>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('my_reports.kicker') }}</span>
                <h1>{{ __('my_reports.title') }}</h1>
                <p>{{ __('my_reports.intro') }}</p>
            </div>
            <div class="fad-hero-actions mr-no-print">
                <a class="fad-btn is-light" href="{{ route('farmer.reports.pdf') }}" data-fast-nav-bound="true" data-my-reports-pdf>{{ __('my_reports.download') }}</a>
                <button type="button" class="fad-btn is-light" onclick="window.print()">{{ __('my_reports.print') }}</button>
                <a class="fad-btn is-quiet" href="{{ route('farmer.my-farm') }}">{{ __('my_reports.back') }}</a>
            </div>
        </header>
        <div class="fad-card">
            @include('farmer-records._my-reports-body')
        </div>
    </div>
    @if ($print)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-app-layout>
