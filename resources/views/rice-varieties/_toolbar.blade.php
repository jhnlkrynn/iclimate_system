{{-- The catalog toolbar, the same for every role: search, sort and a "Filters" button whose panel (closed until opened)
     holds the Farm type / Season / Maturity choices. Active filters show under it as removable chips, with the count
     of varieties at the end of that row. Every choice is a link or the GET form, so it works without scripts.
     $filters: the current filters; $total: how many varieties match. --}}
@php
    $url = function (array $change) use ($filters): string {
        $query = array_merge($filters, $change);
        unset($query['page']);

        return route('rice-varieties.index', array_filter($query, fn ($v) => $v !== null && $v !== ''));
    };
    $groups = [
        'farm_type' => [\App\Models\RiceVariety::FARM_TYPES, fn ($v) => __('rice_varieties.farm_types.'.$v)],
        'season' => [['ds', 'ws'], fn ($v) => __('rice_varieties.seasons.'.$v)],
        'maturity' => [array_keys(\App\Models\RiceVariety::MATURITY_BANDS), fn ($v) => __('rice_varieties.maturity_bands.'.$v)],
    ];
    // Chips for what is on: the panel's filters, a type filter left in the address, and the search.
    $active = [];
    foreach ($groups as $group => [$values, $label]) {
        if (! empty($filters[$group])) {
            $active[] = ['key' => $group, 'label' => $label($filters[$group])];
        }
    }
    if (! empty($filters['type'])) {
        $active[] = ['key' => 'type', 'label' => __('rice_varieties.types.'.$filters['type'])];
    }
    $panelCount = count(array_filter(array_keys($groups), fn ($g) => ! empty($filters[$g])));
    if (! empty($filters['q'])) {
        $active[] = ['key' => 'q', 'label' => __('rice_varieties.catalog.searching', ['q' => $filters['q']])];
    }
@endphp
<div class="rv-toolbar" data-rv-toolbar>
    <form method="GET" action="{{ route('rice-varieties.index') }}" class="rv-toolbar-search" role="search">
        @foreach (array_diff_key($filters, array_flip(['q', 'sort', 'page'])) as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <label class="visually-hidden" for="rvSearch">{{ __('rice_varieties.catalog.search_label') }}</label>
        <span class="rv-search-field">
            <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="9" cy="9" r="5.5" fill="none" stroke="currentColor" stroke-width="1.6"/><path d="m13.2 13.2 3.3 3.3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
            <input type="search" id="rvSearch" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('rice_varieties.catalog.search_placeholder') }}" class="rv-input" data-rv-search>
        </span>
        <label class="visually-hidden" for="rvSort">{{ __('rice_varieties.catalog.sort') }}</label>
        <select id="rvSort" name="sort" class="rv-input rv-sort" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
            @foreach (__('rice_varieties.catalog.sorts') as $value => $label)
                <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button type="submit" class="visually-hidden">{{ __('rice_varieties.catalog.search') }}</button>
    </form>

    <details class="rv-filters" data-rv-filters>
        <summary class="rv-filters-btn" @if ($panelCount) aria-label="{{ __('rice_varieties.catalog.filters') }}, {{ trans_choice('rice_varieties.catalog.filters_active', $panelCount, ['count' => $panelCount]) }}" @endif>
            <svg viewBox="0 0 20 20" aria-hidden="true"><path d="M3 5h14M6 10h8M8.5 15h3" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>
            <span>{{ __('rice_varieties.catalog.filters') }}</span>
            @if ($panelCount)<span class="rv-badge" data-rv-filter-count>{{ $panelCount }}</span>@endif
        </summary>
        <div class="rv-filters-panel" role="group" aria-label="{{ __('rice_varieties.catalog.filters') }}">
            @foreach ($groups as $group => [$values, $label])
                <div class="rv-filter-group" role="group" aria-labelledby="rvFilter-{{ $group }}">
                    <span class="rv-filter-title" id="rvFilter-{{ $group }}">{{ __('rice_varieties.catalog.'.$group) }}</span>
                    <div class="rv-filter-options">
                        <a class="rv-filter {{ empty($filters[$group]) ? 'is-on' : '' }}" href="{{ $url([$group => null]) }}" @if (empty($filters[$group])) aria-current="true" @endif>{{ __('rice_varieties.catalog.all') }}</a>
                        @foreach ($values as $value)
                            @php $on = ($filters[$group] ?? null) === $value; @endphp
                            <a class="rv-filter {{ $on ? 'is-on' : '' }}" href="{{ $url([$group => $on ? null : $value]) }}" @if ($on) aria-current="true" @endif data-rv-filter="{{ $group }}:{{ $value }}">
                                @if ($group === 'maturity')<span class="rv-dot is-{{ $value }}" aria-hidden="true"></span>@endif{{ $label($value) }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>
</div>

<div class="rv-active-row" data-rv-active>
    @if ($active)
        <ul class="rv-active-chips">
            @foreach ($active as $chip)
                <li><a class="rv-active-chip" href="{{ $url([$chip['key'] => null]) }}" aria-label="{{ __('rice_varieties.catalog.remove', ['label' => $chip['label']]) }}" data-rv-active-chip="{{ $chip['key'] }}">{{ $chip['label'] }}<span aria-hidden="true">×</span></a></li>
            @endforeach
        </ul>
        <a class="rv-clear-all" href="{{ route('rice-varieties.index', array_filter(['sort' => $filters['sort'] ?? null])) }}" data-rv-clear-all>{{ __('rice_varieties.catalog.clear_all') }}</a>
    @endif
    <span class="rv-count" data-rv-count>{{ trans_choice('rice_varieties.catalog.results', $total, ['count' => number_format($total)]) }}</span>
</div>
