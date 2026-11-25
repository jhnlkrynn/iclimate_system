<x-app-layout>
    @php
        $title = __('farmer.explore_results.title', [], $locale);
    @endphp
    <x-slot:title>{{ $title }}</x-slot:title>

    <div class="fad frec" data-explore-results>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farmer.explore_results.kicker', [], $locale) }}</span>
                <h1>{{ $title }}</h1>
                <p>{{ __('farmer.explore_results.intro', [], $locale) }}</p>
            </div>
        </header>

        <section class="frec-panel mb-3" aria-label="{{ __('farmer.explore_results.filters_aria', [], $locale) }}">
            <div class="ic-seg mb-3" role="group" aria-label="{{ __('farmer.explore_results.farm_type_aria', [], $locale) }}">
                @foreach (['Irrigated', 'Rainfed'] as $typeOption)
                    <a href="{{ route('farmer.explore-results.index', array_filter(['farm_type' => $typeOption, 'barangay' => $result['barangay'], 'season' => $result['season'], 'year' => $result['year'], 'variety_id' => $result['variety_id']])) }}"
                        @class(['ic-seg-btn', 'is-active' => $result['farm_type'] === $typeOption])
                        @if ($result['farm_type'] === $typeOption) aria-current="page" @endif>
                        {{ $typeOption }}
                    </a>
                @endforeach
            </div>

            <form method="GET" action="{{ route('farmer.explore-results.index') }}" class="row g-2 align-items-end">
                <input type="hidden" name="farm_type" value="{{ $result['farm_type'] }}">
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expBarangay">{{ __('farmer.explore_results.barangay', [], $locale) }}</label>
                    <select id="expBarangay" name="barangay" class="fad-input" data-autosubmit>
                        <option value="">{{ __('farmer.explore_results.all_lian', [], $locale) }}</option>
                        @foreach ($options['barangays'] as $b)
                            <option value="{{ $b }}" @selected($result['barangay'] === $b)>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expSeason">{{ __('farmer.explore_results.season', [], $locale) }}</label>
                    <select id="expSeason" name="season" class="fad-input" data-autosubmit>
                        <option value="">{{ __('farmer.explore_results.all_seasons', [], $locale) }}</option>
                        <option value="Wet" @selected($result['season'] === 'Wet')>Wet</option>
                        <option value="Dry" @selected($result['season'] === 'Dry')>Dry</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expYear">{{ __('farmer.explore_results.year', [], $locale) }}</label>
                    <select id="expYear" name="year" class="fad-input" data-autosubmit>
                        <option value="">{{ __('farmer.explore_results.all_years', [], $locale) }}</option>
                        @foreach ($options['years'] as $y)
                            <option value="{{ $y }}" @selected((int) $result['year'] === (int) $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <label class="fad-label" for="expVariety">{{ __('farmer.explore_results.variety', [], $locale) }}</label>
                    <select id="expVariety" name="variety_id" class="fad-input" data-autosubmit>
                        <option value="">{{ __('farmer.explore_results.all_varieties', [], $locale) }}</option>
                        @foreach ($options['varieties'] as $v)
                            <option value="{{ $v->id }}" @selected((int) $result['variety_id'] === (int) $v->id)>{{ $v->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </section>

        @if (! $result['sufficient'])
            <section class="frec-panel" data-explore-empty>
                <p class="mb-0 fw-semibold" style="color: var(--sage-800, #1f3d28);">{{ $result['insight'] }}</p>
            </section>
        @else
            <section class="frec-panel mb-3" data-explore-insight>
                <p class="mb-1 fw-semibold" style="color: var(--sage-800, #1f3d28);">{{ $result['insight'] }}</p>
                <p class="mb-0 small text-muted" data-explore-based-on>
                    {{ $result['based_on'] }}@if (! empty($result['few_farms_note'])) · {{ $result['few_farms_note'] }}@endif
                </p>
            </section>

            @php $est = $result['estimate'] ?? ['state' => 'none']; @endphp

            <div class="row g-3 mb-3">
                <div class="col-6 col-md-{{ $result['has_harvests'] ? '3' : '4' }}">
                    <div class="frec-panel h-100">
                        <div class="small text-muted">{{ __('farmer.explore_results.farms', [], $locale) }}</div>
                        <div class="fs-4 fw-bold">{{ number_format($result['farm_count']) }}</div>
                    </div>
                </div>
                <div class="col-6 col-md-{{ $result['has_harvests'] ? '3' : '4' }}">
                    <div class="frec-panel h-100">
                        <div class="small text-muted">{{ __('farmer.explore_results.total_area', [], $locale) }}</div>
                        <div class="fs-4 fw-bold">{{ $result['total_area'] !== null ? number_format((float) $result['total_area'], 2).' ha' : '—' }}</div>
                    </div>
                </div>
                @if ($result['has_harvests'])
                    <div class="col-6 col-md-3">
                        <div class="frec-panel h-100">
                            <div class="small text-muted">{{ __('farmer.explore_results.actual_avg', [], $locale) }}</div>
                            <div class="fs-4 fw-bold">{{ number_format((float) $result['avg_yield'], 2) }} <small class="fs-6">t/ha</small></div>
                            <div class="small text-muted">{{ number_format((float) $result['min_yield'], 2) }}–{{ number_format((float) $result['max_yield'], 2) }} t/ha</div>
                        </div>
                    </div>
                @endif
                <div class="col-6 col-md-{{ $result['has_harvests'] ? '3' : '4' }}" data-explore-estimate>
                    <div class="frec-panel h-100">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-1">
                            <span class="small text-muted">{{ __('farmer.explore_results.estimated_yield', [], $locale) }}</span>
                            @if (! empty($est['badge']))
                                <span class="badge rounded-pill text-bg-light border small">{{ $est['badge'] }}</span>
                            @endif
                        </div>
                        @if (($est['state'] ?? 'none') === 'ok')
                            <div class="fs-4 fw-bold">{{ number_format((float) $est['t_ha'], 2) }} <small class="fs-6">t/ha</small></div>
                            @if ($est['total_tons'] !== null)
                                <div class="small fw-semibold">{{ number_format((float) $est['total_tons'], 2) }} t {{ __('farmer.explore_results.total_unit', [], $locale) }}</div>
                            @endif
                            @if ($result['diff_t_ha'] !== null)
                                @php
                                    $sign = $result['diff_t_ha'] > 0 ? '+' : '';
                                    $pctSign = ($result['diff_pct'] ?? 0) > 0 ? '+' : '';
                                @endphp
                                <div class="small text-muted" data-explore-diff>
                                    {{ __('farmer.explore_results.actual_vs_est', [], $locale) }} {{ $sign }}{{ number_format((float) $result['diff_t_ha'], 2) }} t/ha @if ($result['diff_pct'] !== null)({{ $pctSign }}{{ $result['diff_pct'] }}%)@endif
                                </div>
                            @endif
                            <div class="small text-muted mt-1">{{ $est['disclaimer'] }}</div>
                            @if (! empty($est['updated_at']))
                                <div class="small text-muted"><x-updated-at :at="$est['updated_at']" :label="__('farmer.explore_results.updated', [], $locale)" /></div>
                            @endif
                        @else
                            <div class="small text-muted mt-1">{{ $est['none_note'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

            @if (! empty($result['by_season']))
                <section class="frec-panel mb-3" aria-labelledby="expSeasonTitle">
                    <h2 id="expSeasonTitle" class="h5 mb-3">{{ __('farmer.explore_results.season_title', [], $locale) }}</h2>
                    <div style="height: 220px;"><canvas id="exploreSeasonChart"></canvas></div>
                    <script type="application/json" id="exploreSeasonData">@json($result['by_season'])</script>
                </section>
            @endif

            <div class="row g-3">
                <div class="col-md-6">
                    <section class="frec-panel h-100" aria-labelledby="expVarTitle">
                        <h2 id="expVarTitle" class="h5 mb-2">{{ $result['has_harvests'] ? __('farmer.explore_results.top_varieties_title', [], $locale) : __('farmer.explore_results.planted_varieties_title', [], $locale) }}</h2>
                        @if (empty($result['top_varieties']))
                            <p class="text-muted small mb-0">{{ __('farmer.explore_results.no_varieties', [], $locale) }}</p>
                        @else
                            <ul class="list-unstyled mb-0 d-grid gap-2">
                                @foreach ($result['top_varieties'] as $v)
                                    <li class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                        <span class="fw-semibold">{{ $v['name'] }}</span>
                                        <span class="small">@if ($v['avg'] !== null){{ number_format($v['avg'], 2) }} t/ha · @endif{{ $v['farms'] }} {{ $v['farms'] === 1 ? __('farmer.explore_results.farm_singular', [], $locale) : __('farmer.explore_results.farm_plural', [], $locale) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
                <div class="col-md-6">
                    <section class="frec-panel h-100" aria-labelledby="expMonthTitle">
                        <h2 id="expMonthTitle" class="h5 mb-2">{{ __('farmer.explore_results.month_title', [], $locale) }}</h2>
                        @if (empty($result['common_months']))
                            <p class="text-muted small mb-0">{{ __('farmer.explore_results.no_planting_dates', [], $locale) }}</p>
                        @else
                            <ul class="list-unstyled mb-0 d-grid gap-2">
                                @foreach ($result['common_months'] as $m)
                                    <li class="d-flex justify-content-between align-items-center border-bottom pb-1">
                                        <span class="fw-semibold">{{ $m['month'] }}</span>
                                        <span class="small">{{ $m['farms'] }} {{ $m['farms'] === 1 ? __('farmer.explore_results.farm_singular', [], $locale) : __('farmer.explore_results.farm_plural', [], $locale) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                </div>
            </div>
        @endif
    </div>

    <script @nonce>
        document.addEventListener('DOMContentLoaded', () => {
            const el = document.getElementById('exploreSeasonData');
            const canvas = document.getElementById('exploreSeasonChart');
            if (!el || !canvas || !window.Chart) return;
            const rows = JSON.parse(el.textContent || '[]');
            new window.Chart(canvas, {
                type: 'bar',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        label: 't/ha',
                        data: rows.map(r => r.avg),
                        backgroundColor: '#2e6f40',
                        borderRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } },
                },
            });
        });
    </script>
</x-app-layout>
