<x-app-layout>
    {{-- Rice Varieties for farmers and MAO (RiceVarietyController::index). Farmers: "My Planted Variety" and the catalog;
         MAO: the catalog and "Planted in my barangays" (read-only). The catalog is filtered on the server (links and a
         GET form), so it works without scripts; a card opens the Variety Details pop-up (_details-modal). --}}
    @include('rice-varieties._fonts')
    @php
        $day = fn ($date) => $date ? $date->locale(app()->getLocale())->translatedFormat('M j, Y') : '—';
    @endphp

    <div class="fad rv-page" data-rv-page>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('rice_varieties.title') }}</span>
                <h1>{{ __('rice_varieties.title') }}</h1>
                <p>{{ __('rice_varieties.intro') }}</p>
            </div>
        </header>
        @include('rice-varieties._source')

        @if ($isFarmer && $myVariety)
            {{-- A. My Planted Variety --}}
            @if ($myVariety['variety'] && $myVariety['harvest'])
                <section class="rv-mine" aria-labelledby="rvMineTitle" data-rv-mine>
                    <div class="rv-mine-head">
                        <div>
                            <span class="rv-kicker" id="rvMineTitle">{{ __('rice_varieties.my_variety.title') }}</span>
                            <h2 class="rv-mine-name">
                                <button type="button" class="rv-linkish" data-rv-open="{{ $myVariety['variety']->id }}" data-rv-name="{{ $myVariety['variety']->variety_name }}">{{ $myVariety['variety']->variety_name }}</button>
                            </h2>
                            @if ($myVariety['variety']->nsic_reg_no)<span class="rv-pill-mono is-light">{{ $myVariety['variety']->nsic_reg_no }}</span>@endif
                        </div>
                        <a class="rv-btn is-quiet" href="{{ $myVariety['change_url'] }}">{{ __('rice_varieties.my_variety.change') }}</a>
                    </div>

                    <div class="rv-mine-dates">
                        <div>
                            <span class="rv-muted">{{ __('rice_varieties.my_variety.planted_on', ['date' => $day($myVariety['planted'])]) }}</span>
                            <span class="rv-mine-label">{{ __('rice_varieties.my_variety.expected') }}</span>
                            <strong class="rv-mine-date" data-rv-mine-date>{{ $day($myVariety['harvest']) }}</strong>
                            @if ($myVariety['window'])<span class="rv-mine-window">{{ __('rice_varieties.my_variety.window', ['range' => $myVariety['window']]) }}</span>@endif
                        </div>
                        <div class="rv-countdown" data-rv-countdown>
                            @if ($myVariety['days_left'] > 0)
                                <strong>{{ $myVariety['days_left'] }}</strong>
                                <span>{{ trans_choice('rice_varieties.my_variety.to_go', $myVariety['days_left']) }}</span>
                            @elseif ($myVariety['days_left'] === 0)
                                <span>{{ __('rice_varieties.my_variety.due_today') }}</span>
                            @else
                                <span>{{ trans_choice('rice_varieties.my_variety.past', abs($myVariety['days_left'])) }}</span>
                            @endif
                        </div>
                    </div>

                    {{-- Planting → harvest with today marked; the last ~30 days ripening, the 30 before reproductive. --}}
                    <div class="rv-progress" role="img" aria-label="{{ trans_choice('rice_varieties.my_variety.days_to_go', max(0, $myVariety['days_left'])) }}">
                        <div class="rv-progress-track">
                            @foreach ($myVariety['stages'] as $stage)
                                <span class="rv-progress-stage is-{{ $stage['key'] }}" style="left: {{ $stage['from'] }}%; width: {{ max(0, $stage['to'] - $stage['from']) }}%"></span>
                            @endforeach
                            <span class="rv-progress-done" style="width: {{ $myVariety['progress'] }}%"></span>
                            <span class="rv-progress-today" style="left: {{ $myVariety['progress'] }}%"><span>{{ __('rice_varieties.my_variety.today') }}</span></span>
                        </div>
                        <div class="rv-progress-labels">
                            @foreach ($myVariety['stages'] as $stage)
                                @if ($stage['to'] - $stage['from'] > 0)
                                    <span class="is-{{ $stage['key'] }}" style="flex-grow: {{ max(1, $stage['to'] - $stage['from']) }}">{{ __('rice_varieties.my_variety.stages.'.$stage['key']) }}</span>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    @if ($myVariety['basis'])<p class="rv-muted rv-basis" data-rv-basis-text>{{ $myVariety['basis'] }}</p>@endif
                </section>
            @else
                <section class="rv-prompt" aria-labelledby="rvPromptTitle" data-rv-prompt>
                    <svg class="rv-prompt-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21V11m0 0c0-3.5 2.5-6 6-6 0 3.5-2.5 6-6 6Zm0 0c0-3-2-5.5-5.5-5.5C6.5 9 9 11 12 11Z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    <div>
                        <h2 id="rvPromptTitle">{{ __('rice_varieties.prompt.title') }}</h2>
                        <p>{{ __('rice_varieties.prompt.text') }}</p>
                    </div>
                    <a class="rv-btn is-primary" href="{{ route('farmer.planting-schedules.edit', $myVariety['plan']) }}">{{ __('rice_varieties.prompt.button') }}</a>
                </section>
            @endif
        @endif

        {{-- B. Variety catalog --}}
        <section class="rv-catalog" aria-labelledby="rvCatalogTitle">
            <div class="rv-section-head">
                <h2 id="rvCatalogTitle">{{ __('rice_varieties.catalog.title') }}</h2>
            </div>

            {{-- Same toolbar as the IT list: search, sort, Filters (closed until opened), removable filter chips. --}}
            @include('rice-varieties._toolbar', ['filters' => $filters, 'total' => $varieties?->total() ?? 0])

            @if ($varieties && $varieties->isNotEmpty())
                <ul class="rv-cards">
                    @foreach ($varieties as $variety)
                        <li>
                            <button type="button" class="rv-card" data-rv-open="{{ $variety->id }}" data-rv-name="{{ $variety->variety_name }}" aria-label="{{ __('rice_varieties.catalog.open', ['name' => $variety->displayName()]) }}">
                                <span class="rv-card-top">
                                    <span class="rv-card-name">{{ $variety->variety_name }}</span>
                                    @if ($variety->typeKey())<span class="rv-type-pill is-{{ $variety->typeKey() }}">{{ __('rice_varieties.types.'.$variety->variety_type) }}</span>@endif
                                </span>
                                <span class="rv-card-meta"><span class="rv-mono">{{ $variety->nsic_reg_no ?: '—' }}</span>@if ($variety->year_approved)<span>· {{ $variety->year_approved }}</span>@endif</span>
                                <span class="rv-card-figures">
                                    <span><small>{{ __('rice_varieties.catalog.avg_yield') }}</small><strong>{{ $variety->yield_typical ? number_format($variety->yield_typical / 1000, 1).' '.__('rice_varieties.units.t_ha') : __('rice_varieties.catalog.no_data') }}</strong></span>
                                    <span><small>{{ __('rice_varieties.catalog.maturity_days') }}</small><strong class="rv-card-maturity">@if ($variety->maturityClass())<span class="rv-dot is-{{ $variety->maturityClass() }}" title="{{ __('rice_varieties.maturity_bands.'.$variety->maturityClass()) }}"></span>@endif{{ $variety->maturity_typical ? $variety->maturity_typical.' '.__('rice_varieties.units.days') : __('rice_varieties.catalog.no_data') }}</strong></span>
                                </span>
                            </button>
                        </li>
                    @endforeach
                </ul>
                @include('rice-varieties._pager', ['page' => $varieties])
            @elseif ($notLoaded && ! $filtering)
                <div class="rv-empty" data-rv-not-loaded>
                    <p>{{ __('rice_varieties.catalog.unavailable') }}</p>
                </div>
            @else
                <div class="rv-empty" data-rv-no-match>
                    <p>{{ __('rice_varieties.catalog.empty') }}</p>
                    <a class="rv-btn is-quiet" href="{{ route('rice-varieties.index') }}">{{ __('rice_varieties.catalog.clear') }}</a>
                </div>
            @endif
        </section>

        @if ($planted !== null)
            @include('rice-varieties._planted', ['planted' => $planted, 'plantedSort' => $plantedSort, 'day' => $day])
        @endif

        @include('rice-varieties._details-modal')
    </div>
</x-app-layout>
