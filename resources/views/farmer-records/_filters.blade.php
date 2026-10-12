{{-- Filter panel for the farmer record pages: the same search and filters as the staff list (CrudController@index),
     laid out like the Advisories filters. $labels renames a filter (e.g. irrigation_type => Irrigation); $hidden keeps
     a filter that is set elsewhere on the page (e.g. the Unread/Read tabs) in the query. --}}
@php
    $labels = $labels ?? [];
    $hidden = $hidden ?? [];
    $activeFilterCount = (($search ?? '') !== '' ? 1 : 0)
        + collect(array_keys($filterable))->reject(fn ($field) => in_array($field, $hidden, true))->filter(fn ($field) => request()->filled($field))->count();
@endphp
<details class="ic-collapse fad-filters" @if($activeFilterCount) data-force-open="true" @endif>
    <summary>
        <span class="fad-label">Filters</span>
        @if($activeFilterCount)
            <span class="fad-count" aria-label="{{ $activeFilterCount }} active {{ $activeFilterCount === 1 ? 'filter' : 'filters' }}">{{ $activeFilterCount }}</span>
        @endif
    </summary>
    {{-- Panel revision 1.4: results update as you type (resources/js/panel-revisions.js, data-live-filter). --}}
    <form class="fad-filter-form frec-filter-form" method="GET" action="{{ route($routeName.'.index') }}" data-live-filter data-text-none="{{ __('farmer.search.no_results') }}">
        <div class="fad-field is-search">
            <label class="fad-label" for="frecSearch">Search</label>
            <input class="fad-input" id="frecSearch" type="search" name="search" value="{{ $search }}" autocomplete="off" enterkeyhint="search" placeholder="{{ $placeholder ?? 'Search' }}">
        </div>
        @foreach ($filterable as $field => $options)
            @continue(in_array($field, $hidden, true))
            <div class="fad-field">
                <label class="fad-label" for="frecFilter{{ \Illuminate\Support\Str::studly($field) }}">{{ $labels[$field] ?? \Illuminate\Support\Str::headline($field) }}</label>
                <select class="fad-input" id="frecFilter{{ \Illuminate\Support\Str::studly($field) }}" name="{{ $field }}">
                    <option value="">All</option>
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}" @selected((string) request($field) === (string) $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endforeach
        @foreach ($hidden as $field)
            @if(request()->filled($field))<input type="hidden" name="{{ $field }}" value="{{ request($field) }}">@endif
        @endforeach
        <div class="fad-filter-actions">
            <button class="fad-btn is-primary" type="submit">Apply Filters</button>
            <a class="fad-btn is-quiet" href="{{ route($routeName.'.index') }}">Reset</a>
        </div>
    </form>
</details>
