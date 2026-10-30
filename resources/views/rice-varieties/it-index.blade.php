<x-app-layout>
    {{-- Rice Varieties for IT Personnel (RiceVarietyController::index): the seed list as a calm, scannable table. Six
         columns; only exceptions are marked (Manual, Inactive); a row opens the details pop-up; "⋯" holds Edit and
         Deactivate / Activate (no deleting: planting plans point to these rows). Sorted by Newest, the list is grouped
         by year. Below 768px every row is a compact card. --}}
    @include('rice-varieties._fonts')
    @php
        $days = __('rice_varieties.it.days');
        $grouped = $yearCounts !== null;
        $lastYear = false;
    @endphp

    <div class="rv-page rv-it" data-rv-page data-rv-it>
        <header class="rv-it-head">
            <div class="rv-it-title">
                <h1>{{ __('rice_varieties.title') }}</h1>
                @include('rice-varieties._source', ['showImport' => true])
            </div>
            <a class="rv-btn is-primary" href="{{ route('rice-varieties.create') }}" data-rv-add>{{ __('rice_varieties.it.add') }}</a>
        </header>

        @if (session('success'))
            <p class="rv-flash" role="status">{{ session('success') }}</p>
        @endif
        @if (session('error'))
            <p class="rv-flash is-error" role="alert">{{ session('error') }}</p>
        @endif

        @include('rice-varieties._toolbar', ['filters' => $filters, 'total' => $varieties?->total() ?? 0])

        @if ($varieties && $varieties->isNotEmpty())
            {{-- "table-wrap" in the name keeps the app's responsive-table script from boxing it in an overflow: the header must stay sticky and the ⋯ menu unclipped. --}}
            <div class="rv-list-table-wrap">
                <table class="rv-list" data-rv-it-table>
                    <thead><tr>
                        <th scope="col"><span class="rv-th">{{ __('rice_varieties.it.columns.variety') }}</span></th>
                        <th scope="col"><span class="rv-th">{{ __('rice_varieties.it.columns.type') }}</span></th>
                        <th scope="col" class="rv-col-breeder"><span class="rv-th">{{ __('rice_varieties.it.columns.breeder') }}</span></th>
                        <th scope="col"><span class="rv-th">{{ __('rice_varieties.it.columns.maturity') }}</span></th>
                        <th scope="col" class="is-num"><span class="rv-th">{{ __('rice_varieties.it.columns.yield') }}</span></th>
                        <th scope="col" class="rv-col-actions"><span class="visually-hidden">{{ __('rice_varieties.it.columns.actions') }}</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($varieties as $variety)
                            @if ($grouped && $variety->year_approved !== $lastYear)
                                @php $lastYear = $variety->year_approved; @endphp
                                <tr class="rv-year-row" data-rv-year="{{ $lastYear }}">
                                    <th colspan="6" scope="colgroup">{{ $lastYear
                                        ? trans_choice('rice_varieties.catalog.year_group', $yearCounts[$lastYear] ?? 0, ['year' => $lastYear, 'count' => $yearCounts[$lastYear] ?? 0])
                                        : __('rice_varieties.catalog.no_year') }}</th>
                                </tr>
                            @endif
                            <tr class="rv-row @if (! $variety->is_active) is-inactive @endif" data-rv-open="{{ $variety->id }}" data-rv-name="{{ $variety->variety_name }}" tabindex="0"
                                aria-label="{{ __('rice_varieties.catalog.open', ['name' => $variety->displayName()]) }}">
                                <td class="rv-cell-variety">
                                    <span class="rv-name-line">
                                        <span class="rv-name">{{ $variety->variety_name }}</span>
                                        @if ($variety->source === \App\Models\RiceVariety::SOURCE_MANUAL)<span class="rv-pill is-manual" data-rv-manual>{{ __('rice_varieties.it.manual') }}</span>@endif
                                        @if (! $variety->is_active)<span class="rv-pill is-inactive" data-rv-inactive>{{ __('rice_varieties.it.inactive') }}</span>@endif
                                    </span>
                                    <span class="rv-nsic">{{ $variety->nsic_reg_no ?: '—' }}</span>
                                </td>
                                <td class="rv-cell-type">
                                    @if ($variety->typeKey())<span class="rv-type-pill is-{{ $variety->typeKey() }}">{{ __('rice_varieties.types.'.$variety->variety_type) }}</span>@endif
                                    @if ($variety->year_approved)<span class="rv-sub">{{ $variety->typeKey() ? '· ' : '' }}{{ $variety->year_approved }}</span>@endif
                                </td>
                                <td class="rv-col-breeder"><span class="rv-breeder" title="{{ $variety->owner_breeder }}">{{ $variety->breederShort() ?? '—' }}</span></td>
                                <td class="rv-cell-maturity">
                                    @if ($variety->maturityClass())<span class="rv-dot is-{{ $variety->maturityClass() }}" title="{{ __('rice_varieties.maturity_bands.'.$variety->maturityClass()) }}"></span>@else<span class="rv-dot is-none" aria-hidden="true"></span>@endif
                                    <span class="rv-pair"><span class="rv-pair-label">DS</span><b>{{ $variety->maturity_tpr_ds ?? '—' }}</b></span><span class="rv-sep" aria-hidden="true">·</span><span class="rv-pair"><span class="rv-pair-label">WS</span><b>{{ $variety->maturity_tpr_ws ?? '—' }}</b></span>
                                    <span class="rv-unit">{{ $days }}</span>
                                </td>
                                <td class="is-num rv-cell-yield">{{ $variety->yield_typical ? number_format($variety->yield_typical / 1000, 1).' '.__('rice_varieties.units.t_ha') : '—' }}</td>
                                <td class="rv-col-actions">@include('rice-varieties._row-menu', ['variety' => $variety])</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @include('rice-varieties._pager', ['page' => $varieties])
        @elseif ($notLoaded && ! $filtering)
            {{-- Nothing imported yet: IT loads the NSIC list here (the same import as rice-varieties:import). --}}
            <div class="rv-empty" data-rv-not-loaded>
                <p><strong>{{ __('rice_varieties.it.not_loaded') }}</strong></p>
                <p>{{ __('rice_varieties.it.not_loaded_hint') }}</p>
                <form method="POST" action="{{ route('rice-varieties.import') }}" data-loading="true">
                    @csrf
                    <button type="submit" class="rv-btn is-primary" data-rv-run-import data-loading-text="{{ __('rice_varieties.it.importing') }}">{{ __('rice_varieties.it.run_import') }}</button>
                </form>
            </div>
        @else
            <div class="rv-empty" data-rv-no-match>
                <p>{{ __('rice_varieties.catalog.empty') }}</p>
                <a class="rv-btn is-quiet" href="{{ route('rice-varieties.index') }}">{{ __('rice_varieties.catalog.clear') }}</a>
            </div>
        @endif

        @include('rice-varieties._details-modal')
    </div>
</x-app-layout>
