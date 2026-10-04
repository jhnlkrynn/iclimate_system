<x-app-layout>
    @php
        $num = fn ($value, int $decimals = 2) => $value === null ? '—' : number_format((float) $value, $decimals);
        $maxYield = $yieldBars->max('yield_per_hectare') ?: 0;
        $month = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->format('M Y') : '—';
    @endphp

    <div class="fad frec">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Rice Production</span>
                <h1>Rice Production</h1>
                <p>Rice production and harvest records for Lian, by barangay and season, kept by the Municipal Agriculture Office.</p>
            </div>
            @if($canManage)
                <div class="fad-hero-actions"><a class="fad-btn is-light" href="{{ route('rice-productions.create') }}">Add Production Record</a></div>
            @endif
        </header>

        <section class="frec-kpis" aria-label="Summary of the records shown">
            <article class="frec-kpi">
                <h2 class="fad-label">Records</h2>
                <p class="frec-kpi-value">{{ number_format((int) $stats->records) }}</p>
                <p class="fad-support">
                    @if($farmerBarangay){{ $ownBarangayCount }} from your barangay ({{ $farmerBarangay }})@else Add your barangay in your profile to see yours @endif
                </p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Total Production</h2>
                <p class="frec-kpi-value">{{ $num($stats->total_production, 1) }}@if($stats->total_production !== null)<small>t</small>@endif</p>
                <p class="fad-support">Tonnes, added up across the records shown</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Average Yield</h2>
                <p class="frec-kpi-value">{{ $num($stats->avg_yield) }}@if($stats->avg_yield !== null)<small>t/ha</small>@endif</p>
                <p class="fad-support">Average of the records' yield per hectare</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Harvested Area</h2>
                <p class="frec-kpi-value">{{ $num($stats->total_area, 1) }}@if($stats->total_area !== null)<small>ha</small>@endif</p>
                <p class="fad-support">{{ $stats->latest_year ? 'Latest record: '.$stats->latest_year : 'No records match' }}</p>
            </article>
        </section>

        @include('farmer-records._filters', ['placeholder' => 'Search by barangay, season, year or remarks', 'labels' => ['irrigation_type' => 'Irrigation']])

        @if($yieldBars->isNotEmpty())
            <section class="frec-panel" aria-labelledby="frecYieldTitle">
                <div class="frec-panel-head">
                    <h2 id="frecYieldTitle">Yield per Hectare</h2>
                    <p class="fad-support">Tonnes per hectare in each record, oldest first. Your barangay is marked.</p>
                </div>
                <ol class="frec-bars is-yield">
                    @foreach($yieldBars as $bar)
                        @php $mine = $farmerBarangay && $bar->barangay === $farmerBarangay; @endphp
                        <li @class(['is-mine' => $mine])>
                            <span class="frec-bar-label">{{ $bar->year }} {{ $bar->season }} · {{ $bar->barangay }}@if($mine) <span class="fad-badge is-good">Yours</span>@endif</span>
                            <span class="frec-bar" role="img" aria-label="{{ $bar->year }} {{ $bar->season }} season, {{ $bar->barangay }}: {{ $num($bar->yield_per_hectare) }} tonnes per hectare"><i @style(['width: '.($maxYield > 0 ? max(1, round($bar->yield_per_hectare / $maxYield * 100)) : 0).'%'])></i></span>
                            <span class="frec-bar-value">{{ $num($bar->yield_per_hectare) }} t/ha</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="frec-panel" aria-labelledby="frecListTitle">
            <div class="frec-panel-head is-row">
                <h2 id="frecListTitle">Production Records</h2>
                <p class="fad-support">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</p>
            </div>
            @if($records->count())
                <div class="frec-table-wrap">
                    <table class="frec-table">
                        <thead>
                            <tr>
                                <th scope="col">Barangay</th>
                                <th scope="col">Year</th>
                                <th scope="col">Season</th>
                                <th scope="col">Irrigation</th>
                                <th scope="col">Harvest Month</th>
                                <th scope="col" class="is-num">Area (ha)</th>
                                <th scope="col" class="is-num">Yield (t/ha)</th>
                                <th scope="col" class="is-num">Production (t)</th>
                                <th scope="col" class="is-end"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr @class(['is-mine' => $farmerBarangay && $record->barangay === $farmerBarangay])>
                                    <th scope="row">{{ $record->barangay ?: '—' }}@if($farmerBarangay && $record->barangay === $farmerBarangay) <span class="fad-badge is-good">Yours</span>@endif</th>
                                    <td>{{ $record->year ?: '—' }}</td>
                                    <td>@if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@else — @endif</td>
                                    <td>{{ $record->irrigation_type ?: '—' }}</td>
                                    <td>{{ $month($record->production_month) }}</td>
                                    <td class="is-num">{{ $num($record->area_hectares) }}</td>
                                    <td class="is-num">{{ $num($record->yield_per_hectare) }}</td>
                                    <td class="is-num">{{ $num($record->total_production) }}</td>
                                    <td class="is-end">
                                        <a class="fad-btn is-quiet is-compact" href="{{ route('rice-productions.show', $record) }}" data-ic-view data-ic-size="normal" aria-haspopup="dialog" aria-label="View {{ $record->barangay }} {{ $record->year }} {{ $record->season }} record">View</a>
                                        @if($canManage)
                                            <a class="fad-btn is-quiet is-compact" href="{{ route('rice-productions.edit', $record) }}">Edit</a>
                                            <form class="d-inline" method="POST" action="{{ route('rice-productions.destroy', $record) }}" data-loading="true" data-ic-confirm="Delete this record? This action cannot be undone.">
                                                @csrf @method('DELETE')
                                                <button class="fad-btn is-quiet is-compact is-danger" type="submit" data-loading-text="Deleting...">Delete</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="frec-cards">
                    @foreach($records as $record)
                        <li class="frec-card">
                            <div class="frec-card-head">
                                <h3>{{ $record->barangay ?: '—' }} · {{ $record->year }}</h3>
                                @if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@endif
                            </div>
                            @if($farmerBarangay && $record->barangay === $farmerBarangay)<span class="fad-badge is-good">Your barangay</span>@endif
                            <dl class="frec-facts">
                                <div><dt>Irrigation</dt><dd>{{ $record->irrigation_type ?: '—' }}</dd></div>
                                <div><dt>Harvest month</dt><dd>{{ $month($record->production_month) }}</dd></div>
                                <div><dt>Area</dt><dd>{{ $num($record->area_hectares) }} ha</dd></div>
                                <div><dt>Yield</dt><dd>{{ $num($record->yield_per_hectare) }} t/ha</dd></div>
                                <div><dt>Production</dt><dd>{{ $num($record->total_production) }} t</dd></div>
                            </dl>
                            <a class="fad-btn is-quiet" href="{{ route('rice-productions.show', $record) }}" data-ic-view data-ic-size="normal" aria-haspopup="dialog">View Record</a>
                        </li>
                    @endforeach
                </ul>
                @if($records->hasPages())<div class="fad-pages">{{ $records->links() }}</div>@endif
            @else
                <div class="fad-empty">
                    <p class="fad-lead">No rice production records found.</p>
                    <p class="fad-body">{{ ($search ?? '') !== '' || request()->hasAny(array_keys($filterable)) ? 'Try changing the search or filters.' : 'Records added by the Municipal Agriculture Office will appear here.' }}</p>
                    @if(($search ?? '') !== '' || request()->hasAny(array_keys($filterable)))<a class="fad-btn is-quiet" href="{{ route('rice-productions.index') }}">Reset Filters</a>@endif
                </div>
            @endif
        </section>

        <section class="frec-note" aria-labelledby="frecAboutTitle">
            <h2 id="frecAboutTitle" class="fad-label">About these records</h2>
            <p class="fad-support">These are barangay-level production records entered by the Municipal Agriculture Office. To record your own planting and harvest, use <a href="{{ route('calendar.index') }}">My Planting and Harvest Records</a> on the Calendar.</p>
        </section>
    </div>
</x-app-layout>
