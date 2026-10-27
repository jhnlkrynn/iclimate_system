{{-- MAO: "Planted in my barangays" (App\Services\RiceVarieties\PlantedVarietiesOverview), scoped like the other MAO
     pages (App\Support\MaoScope). Read-only; a variety name opens the Variety Details pop-up. --}}
<section class="rv-planted" aria-labelledby="rvPlantedTitle" data-rv-planted>
    <div class="rv-section-head">
        <h2 id="rvPlantedTitle">{{ __('rice_varieties.mao.title') }}</h2>
        <span class="rv-muted" data-rv-scope>{{ $planted['scope'] === null ? __('rice_varieties.mao.all') : implode(', ', $planted['scope']) }}</span>
    </div>

    <div class="rv-tiles">
        <div class="rv-tile"><strong data-rv-tile="with">{{ $planted['with_variety'] }}</strong><span>{{ __('rice_varieties.mao.with_variety') }}</span></div>
        <div class="rv-tile"><strong data-rv-tile="without">{{ $planted['without_variety'] }}</strong><span>{{ __('rice_varieties.mao.without_variety') }}</span></div>
        @foreach ($planted['soon'] as $days => $count)
            <div class="rv-tile"><strong data-rv-tile="soon-{{ $days }}">{{ $count }}</strong><span>{{ __('rice_varieties.mao.soon', ['days' => $days]) }}</span></div>
        @endforeach
        <div class="rv-tile is-wide">
            <span>{{ __('rice_varieties.mao.top') }}</span>
            @if ($planted['top'])
                <ol class="rv-top" data-rv-top>
                    @foreach ($planted['top'] as $top)
                        <li><button type="button" class="rv-linkish" data-rv-open="{{ $top['id'] }}" data-rv-name="{{ $top['name'] }}">{{ $top['name'] }}</button><span>{{ trans_choice('rice_varieties.mao.farms', $top['count'], ['count' => $top['count']]) }}</span></li>
                    @endforeach
                </ol>
            @else
                <p class="rv-muted">—</p>
            @endif
        </div>
    </div>

    @if ($planted['rows']->isNotEmpty())
        <div class="rv-table-wrap">
            <table class="rv-table" data-rv-planted-table>
                <thead><tr>
                    <th scope="col">{{ __('rice_varieties.mao.columns.farmer') }}</th>
                    <th scope="col">{{ __('rice_varieties.mao.columns.barangay') }}</th>
                    <th scope="col">{{ __('rice_varieties.mao.columns.variety') }}</th>
                    <th scope="col">{{ __('rice_varieties.mao.columns.establishment') }}</th>
                    <th scope="col">{{ __('rice_varieties.mao.columns.planted') }}</th>
                    <th scope="col" aria-sort="{{ $plantedSort === 'desc' ? 'descending' : 'ascending' }}">
                        <a href="{{ request()->fullUrlWithQuery(['planted_sort' => $plantedSort === 'desc' ? 'asc' : 'desc']) }}#rvPlantedTitle">{{ __('rice_varieties.mao.columns.harvest') }} <span aria-hidden="true">{{ $plantedSort === 'desc' ? '↓' : '↑' }}</span></a>
                    </th>
                    <th scope="col" class="is-num">{{ __('rice_varieties.mao.columns.days_left') }}</th>
                </tr></thead>
                <tbody>
                    @foreach ($planted['rows'] as $row)
                        <tr>
                            <th scope="row">{{ $row['farmer'] ?? '—' }}</th>
                            <td>{{ $row['barangay'] ?? '—' }}</td>
                            <td>
                                @if ($row['variety_id'])
                                    <button type="button" class="rv-linkish" data-rv-open="{{ $row['variety_id'] }}" data-rv-name="{{ $row['variety'] }}">{{ $row['variety'] }}</button>
                                @else
                                    <span class="rv-muted">{{ __('rice_varieties.mao.not_recorded') }}</span>
                                @endif
                            </td>
                            <td>{{ $row['establishment'] ? __('rice_varieties.form.establishments.'.$row['establishment']) : '—' }}</td>
                            <td>{{ $day($row['planted']) }}</td>
                            <td>{{ $day($row['harvest']) }}</td>
                            <td class="is-num">{{ $row['days_left'] === null ? '—' : ($row['days_left'] < 0 ? trans_choice('rice_varieties.mao.overdue', abs($row['days_left']), ['count' => abs($row['days_left'])]) : $row['days_left']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="rv-empty">{{ __('rice_varieties.mao.none') }}</p>
    @endif
</section>
