{{-- The Variety Details pop-up (one per page). Opened by any [data-rv-open="ID"]; its data comes from
     GET /rice-varieties/{id} (RiceVarietyController::show) and is filled in by resources/js/rice-varieties.js. A native
     <dialog>: Esc and a click outside close it, and the page behind cannot be reached while it is open. Full screen on
     a phone, a 720px dialog on a wider screen. --}}
@php
    $i18n = [
        'loading' => __('rice_varieties.modal.loading'),
        'released' => __('rice_varieties.modal.released', ['year' => ':year']),
        'range_avg' => __('rice_varieties.modal.range_avg', ['days' => ':days']),
        'calendar_note' => __('rice_varieties.modal.calendar_note', ['date' => ':date']),
        'tillers_unit_tpr' => __('rice_varieties.modal.tillers_unit_tpr'),
        'tillers_unit_dwsr' => __('rice_varieties.modal.tillers_unit_dwsr'),
        'no_trial' => __('rice_varieties.modal.no_trial'),
        'none' => __('rice_varieties.modal.none'),
    ];
    $stats = [
        'yield' => [__('rice_varieties.modal.avg_yield'), __('rice_varieties.units.t_ha')],
        'max' => [__('rice_varieties.modal.max_yield'), __('rice_varieties.units.t_ha')],
        'maturity' => [__('rice_varieties.modal.maturity'), __('rice_varieties.units.das')],
        'height' => [__('rice_varieties.modal.height'), __('rice_varieties.units.cm')],
    ];
@endphp
<dialog class="rv-modal" data-rv-dialog aria-labelledby="rvModalTitle" data-rv-show-url="{{ route('rice-varieties.show', ['riceVariety' => '__ID__']) }}">
    <script type="application/json" data-rv-i18n>@json($i18n)</script>
    <div class="rv-sheet">
        <header class="rv-head">
            {{-- A grain panicle and leaf, drawn here so the pop-up needs no image. --}}
            <svg class="rv-head-motif" viewBox="0 0 160 120" aria-hidden="true" focusable="false">
                <path d="M118 118C112 86 104 58 86 26" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/>
                @foreach ([[98, 46, -28], [104, 60, -22], [109, 74, -16], [92, 34, -34], [84, 22, -40]] as [$x, $y, $r])
                    <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="4.2" ry="9" transform="rotate({{ $r }} {{ $x }} {{ $y }})" fill="currentColor" opacity=".9"/>
                    <ellipse cx="{{ $x - 11 }}" cy="{{ $y + 5 }}" rx="3.6" ry="8" transform="rotate({{ $r - 34 }} {{ $x - 11 }} {{ $y + 5 }})" fill="currentColor" opacity=".7"/>
                @endforeach
                <path d="M126 118c-2-30 8-58 30-80-8 32-14 56-30 80Z" fill="currentColor" opacity=".45"/>
                <path d="M110 118c-18-18-40-26-66-26 22 10 42 18 66 26Z" fill="currentColor" opacity=".3"/>
            </svg>
            <div class="rv-head-main">
                <h2 class="rv-head-title" id="rvModalTitle" data-rv-f="name"></h2>
                <span class="rv-pill-mono" data-rv-f="nsic" hidden></span>
                <p class="rv-head-line" data-rv-f="line_name" hidden></p>
            </div>
            <div class="rv-head-side">
                <span class="rv-head-breeder" data-rv-f="breeder" hidden></span>
                <span class="rv-head-chip" data-rv-f="released" hidden></span>
                <span class="rv-head-type" data-rv-f="type" hidden></span>
            </div>
            <button type="button" class="rv-close" data-rv-close aria-label="{{ __('rice_varieties.modal.close') }}">
                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true"><path d="M4.5 4.5l9 9M13.5 4.5l-9 9" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        </header>

        <div class="rv-body">
            <p class="rv-state" data-rv-loading role="status">{{ __('rice_varieties.modal.loading') }}</p>
            <p class="rv-state is-error" data-rv-error role="alert" hidden>{{ __('rice_varieties.modal.failed') }}</p>

            <div data-rv-content hidden>
                <div class="rv-switches">
                    <div class="rv-segctl" role="radiogroup" aria-label="{{ __('rice_varieties.modal.season_label') }}">
                        @foreach (['ds', 'ws'] as $season)
                            <button type="button" role="radio" aria-checked="false" data-rv-season="{{ $season }}" title="{{ __('rice_varieties.seasons.'.$season) }}">{{ __('rice_varieties.modal.'.$season) }}</button>
                        @endforeach
                    </div>
                    <div class="rv-segctl" role="radiogroup" aria-label="{{ __('rice_varieties.modal.method_label') }}">
                        @foreach (['tpr', 'dwsr'] as $method)
                            <button type="button" role="radio" aria-checked="false" data-rv-method="{{ $method }}">{{ __('rice_varieties.modal.'.$method) }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="rv-stats">
                    @foreach ($stats as $key => [$label, $unit])
                        <div class="rv-stat" data-rv-stat="{{ $key }}">
                            <span class="rv-stat-figure"><span class="rv-stat-value" data-rv-stat-value>—</span><span class="rv-stat-unit" data-rv-stat-unit>{{ $unit }}</span></span>
                            <span class="rv-stat-label">{{ $label }}</span>
                            <span class="rv-stat-note" data-rv-stat-note hidden>{{ __('rice_varieties.modal.no_trial') }}</span>
                        </div>
                    @endforeach
                </div>

                <section class="rv-range" aria-labelledby="rvRangeTitle">
                    <h3 id="rvRangeTitle" class="rv-subtitle">{{ __('rice_varieties.modal.range_title') }}</h3>
                    <div data-rv-range>
                        <div class="rv-bar"><span class="rv-bar-fill" data-rv-range-fill></span><span class="rv-bar-avg" data-rv-range-avg></span></div>
                        <div class="rv-bar-labels"><span data-rv-range-min></span><strong data-rv-range-avgtext></strong><span data-rv-range-max></span></div>
                        <p class="rv-muted" data-rv-range-note hidden>{{ __('rice_varieties.modal.range_none') }}</p>
                    </div>
                    <p class="rv-muted" data-rv-range-empty hidden>— {{ __('rice_varieties.modal.no_trial') }}</p>
                    <div class="rv-calendar" data-rv-calendar hidden>
                        <h4 class="rv-subtitle is-small">{{ __('rice_varieties.modal.calendar_title') }}</h4>
                        <div class="rv-bar is-calendar"><span class="rv-bar-fill" data-rv-cal-fill></span><span class="rv-bar-avg" data-rv-cal-avg></span></div>
                        <div class="rv-bar-labels"><span data-rv-cal-start></span><strong data-rv-cal-avgtext></strong><span data-rv-cal-end></span></div>
                        <p class="rv-muted" data-rv-cal-note></p>
                    </div>
                </section>

                <div class="rv-tabs" role="tablist" aria-label="{{ __('rice_varieties.modal.tabs.agronomy') }}">
                    @foreach (['agronomy', 'grain'] as $tab)
                        <button type="button" role="tab" id="rvTab-{{ $tab }}" aria-controls="rvPanel-{{ $tab }}" aria-selected="{{ $tab === 'agronomy' ? 'true' : 'false' }}" tabindex="{{ $tab === 'agronomy' ? 0 : -1 }}" data-rv-tab="{{ $tab }}">{{ __('rice_varieties.modal.tabs.'.$tab) }}</button>
                    @endforeach
                </div>

                <div class="rv-panel" role="tabpanel" id="rvPanel-agronomy" aria-labelledby="rvTab-agronomy">
                    <dl class="rv-facts">
                        <div><dt>{{ __('rice_varieties.modal.tillers') }}</dt><dd data-rv-a="tillers"></dd></div>
                        <div><dt>{{ __('rice_varieties.modal.culm') }}</dt><dd data-rv-a="culm"></dd></div>
                        <div><dt>{{ __('rice_varieties.modal.height_range') }}</dt><dd data-rv-a="height"></dd></div>
                    </dl>
                    <table class="rv-yield-table">
                        <caption>{{ __('rice_varieties.modal.yield_table') }}</caption>
                        <thead><tr><th scope="col"></th><th scope="col">{{ __('rice_varieties.modal.ds') }}</th><th scope="col">{{ __('rice_varieties.modal.ws') }}</th></tr></thead>
                        <tbody>
                            @foreach (['tpr', 'dwsr'] as $method)
                                <tr><th scope="row">{{ __('rice_varieties.modal.'.$method) }}</th><td data-rv-yield-cell="{{ $method }}_ds">—</td><td data-rv-yield-cell="{{ $method }}_ws">—</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <div class="rv-regions" data-rv-regions hidden>
                        <h4 class="rv-subtitle is-small">{{ __('rice_varieties.modal.regional') }}</h4>
                        <ul></ul>
                    </div>
                </div>

                <div class="rv-panel" role="tabpanel" id="rvPanel-grain" aria-labelledby="rvTab-grain" hidden>
                    <div class="rv-amylose" data-rv-amylose>
                        <span class="rv-amylose-title">{{ __('rice_varieties.modal.amylose') }}</span>
                        <strong class="rv-amylose-value" data-rv-amylose-value></strong>
                        <span class="rv-amylose-label" data-rv-amylose-label></span>
                    </div>
                    <p class="rv-muted" data-rv-amylose-empty hidden>{{ __('rice_varieties.modal.amylose') }}: —</p>
                    <h4 class="rv-subtitle is-small">{{ __('rice_varieties.modal.grain_facts') }}</h4>
                    <ul class="rv-bullets" data-rv-facts></ul>
                </div>

                <section class="rv-reco" aria-labelledby="rvRecoTitle">
                    <h3 id="rvRecoTitle">{{ __('rice_varieties.modal.recommendation') }}</h3>
                    <dl>
                        <div><dt>{{ __('rice_varieties.modal.location') }}</dt><dd data-rv-reco="location"></dd></div>
                        <div><dt>{{ __('rice_varieties.modal.farm_type') }}</dt><dd class="rv-farm-chips" data-rv-farm-types></dd></div>
                        @foreach (['season', 'establishment'] as $key)
                            <div><dt>{{ __('rice_varieties.modal.'.$key) }}</dt><dd data-rv-reco="{{ $key }}"></dd></div>
                        @endforeach
                    </dl>
                    <p class="rv-reco-text"><span class="visually-hidden">{{ __('rice_varieties.modal.adaptation') }}: </span><span data-rv-reco="adaptation"></span></p>
                </section>

                <a class="rv-btn is-primary rv-plant" href="#" data-rv-plant hidden>{{ __('rice_varieties.modal.planted_this') }}</a>
            </div>
        </div>
    </div>
</dialog>
