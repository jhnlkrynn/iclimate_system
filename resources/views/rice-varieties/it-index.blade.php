<x-app-layout>
    {{-- Rice Varieties for IT Personnel (RiceVarietyController::index): the seed list only, as a data table. Add / Edit
         (rice-varieties.form) and an Active switch instead of delete, since planting plans point to these rows. Type
         roles of the IT dashboard: Sora titles, DM Sans text, JetBrains Mono figures. --}}
    @include('rice-varieties._fonts')
    @php
        $chipUrl = function (string $key, ?string $value) use ($filters): string {
            $query = $filters;
            $value === null ? \Illuminate\Support\Arr::forget($query, $key) : $query[$key] = $value;

            return route('rice-varieties.index', $query);
        };
        $chipGroups = [
            'farm_type' => \App\Models\RiceVariety::FARM_TYPES,
            'season' => ['ds', 'ws'],
            'maturity' => array_keys(\App\Models\RiceVariety::MATURITY_BANDS),
        ];
        $chipLabel = fn (string $group, string $value): string => match ($group) {
            'farm_type' => __('rice_varieties.farm_types.'.$value),
            'season' => __('rice_varieties.seasons.'.$value),
            'maturity' => __('rice_varieties.maturity_bands.'.$value),
        };
    @endphp

    <div class="rv-page rv-it" data-rv-page data-rv-it>
        <header class="rv-it-head">
            <div>
                <h1>{{ __('rice_varieties.title') }}</h1>
                <p>{{ __('rice_varieties.intro') }}</p>
            </div>
            <a class="rv-btn is-primary" href="{{ route('rice-varieties.create') }}" data-rv-add>{{ __('rice_varieties.it.add') }}</a>
        </header>
        @include('rice-varieties._source', ['showImport' => true])

        @if (session('success'))
            <p class="rv-flash" role="status">{{ session('success') }}</p>
        @endif

        <form method="GET" action="{{ route('rice-varieties.index') }}" class="rv-search" role="search">
            @foreach (array_diff_key($filters, array_flip(['q', 'sort'])) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <label class="visually-hidden" for="rvSearch">{{ __('rice_varieties.catalog.search_label') }}</label>
            <input type="search" id="rvSearch" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('rice_varieties.catalog.search_placeholder') }}" class="rv-input">
            <label class="visually-hidden" for="rvSort">{{ __('rice_varieties.catalog.sort') }}</label>
            <select id="rvSort" name="sort" class="rv-input rv-sort" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                @foreach (__('rice_varieties.catalog.sorts') as $value => $label)
                    <option value="{{ $value }}" @selected(($filters['sort'] ?? 'newest') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" class="rv-btn is-primary">{{ __('rice_varieties.catalog.search') }}</button>
        </form>

        <div class="rv-chip-groups">
            @foreach ($chipGroups as $group => $values)
                <div class="rv-chip-row" role="group" aria-label="{{ __('rice_varieties.catalog.'.$group) }}">
                    <span class="rv-chip-row-label">{{ __('rice_varieties.catalog.'.$group) }}</span>
                    <a class="rv-filter {{ empty($filters[$group]) ? 'is-on' : '' }}" href="{{ $chipUrl($group, null) }}">{{ __('rice_varieties.catalog.all') }}</a>
                    @foreach ($values as $value)
                        @php $on = ($filters[$group] ?? null) === $value; @endphp
                        <a class="rv-filter {{ $on ? 'is-on' : '' }}" href="{{ $chipUrl($group, $on ? null : $value) }}" @if ($on) aria-current="true" @endif data-rv-filter="{{ $group }}:{{ $value }}">{{ $chipLabel($group, $value) }}</a>
                    @endforeach
                </div>
            @endforeach
        </div>

        @if ($varieties && $varieties->isNotEmpty())
            <p class="rv-muted" data-rv-count>{{ trans_choice('rice_varieties.catalog.results', $varieties->total(), ['count' => number_format($varieties->total())]) }}</p>
            <div class="rv-table-wrap">
                <table class="rv-table is-dense" data-rv-it-table>
                    <thead><tr>
                        @foreach (['nsic', 'name', 'type', 'year', 'breeder', 'maturity', 'yield', 'source', 'active'] as $column)
                            <th scope="col" @class(['is-num' => in_array($column, ['year', 'maturity', 'yield'], true)])>{{ __('rice_varieties.it.columns.'.$column) }}</th>
                        @endforeach
                        <th scope="col"><span class="visually-hidden">{{ __('rice_varieties.it.edit') }}</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($varieties as $variety)
                            <tr data-rv-open="{{ $variety->id }}" data-rv-name="{{ $variety->variety_name }}" tabindex="0" @class(['is-inactive' => ! $variety->is_active])>
                                <td class="rv-mono">{{ $variety->nsic_reg_no ?: '—' }}</td>
                                <th scope="row">{{ $variety->variety_name }}</th>
                                <td>{{ $variety->variety_type ? __('rice_varieties.types.'.$variety->variety_type) : '—' }}</td>
                                <td class="is-num rv-mono">{{ $variety->year_approved ?? '—' }}</td>
                                <td>{{ $variety->owner_breeder ?: '—' }}</td>
                                <td class="is-num rv-mono">{{ $variety->maturity_tpr_ds ?? '—' }} / {{ $variety->maturity_tpr_ws ?? '—' }}</td>
                                <td class="is-num rv-mono">{{ $variety->yield_typical ? number_format($variety->yield_typical / 1000, 1) : '—' }}</td>
                                <td><span class="rv-source is-{{ $variety->source }}">{{ __('rice_varieties.it.source.'.$variety->source) }}</span></td>
                                <td>
                                    <form method="POST" action="{{ route('rice-varieties.toggle', $variety) }}" class="rv-toggle-form" data-rv-stop>
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="rv-switch" role="switch" aria-checked="{{ $variety->is_active ? 'true' : 'false' }}" aria-label="{{ __('rice_varieties.it.active') }}: {{ $variety->variety_name }}" data-rv-toggle="{{ $variety->id }}"><span></span></button>
                                    </form>
                                </td>
                                <td><a class="rv-btn is-quiet is-compact" href="{{ route('rice-varieties.edit', $variety) }}" data-rv-stop aria-label="{{ __('rice_varieties.it.edit') }}: {{ $variety->variety_name }}">{{ __('rice_varieties.it.edit_short') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="rv-pages">{{ $varieties->onEachSide(1)->links() }}</div>
        @else
            <div class="rv-empty">
                <p>{{ __('rice_varieties.catalog.empty') }}</p>
                <a class="rv-btn is-quiet" href="{{ route('rice-varieties.index') }}">{{ __('rice_varieties.catalog.clear') }}</a>
            </div>
        @endif

        @include('rice-varieties._details-modal')
    </div>
</x-app-layout>
