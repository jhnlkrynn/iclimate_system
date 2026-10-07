<x-app-layout>
    @php
        $num = fn ($value, int $decimals = 1) => $value === null ? '—' : number_format((float) $value, $decimals);
        $maxRain = $rainBars->max('rainfall') ?: 0;
        $wettest = $rainBars->sortByDesc('rainfall')->first();
    @endphp

    <div class="fad frec">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Climate Records</span>
                <h1>Climate Records for Lian, Batangas</h1>
                <p>Past weather records iClimate uses for Lian, Batangas.</p>
            </div>
        </header>

        <section class="frec-kpis" aria-label="Summary of the records shown">
            <article class="frec-kpi">
                <h2 class="fad-label">Records</h2>
                <p class="frec-kpi-value">{{ number_format((int) $stats->records) }}</p>
                <p class="fad-support">{{ $stats->records ? $stats->first_label.' – '.$stats->last_label : 'No records match' }}</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Average Temperature</h2>
                <p class="frec-kpi-value">{{ $num($stats->avg_temp) }}@if($stats->avg_temp !== null)<small>°C</small>@endif</p>
                <p class="fad-support">@if($stats->max_temp !== null)Highest {{ $num($stats->max_temp) }}°C · Lowest {{ $num($stats->min_temp) }}°C @else No temperature recorded @endif</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Total Rainfall</h2>
                <p class="frec-kpi-value">{{ $num($stats->total_rain) }}@if($stats->total_rain !== null)<small>mm</small>@endif</p>
                <p class="fad-support">Added up across the records shown</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Average Humidity</h2>
                <p class="frec-kpi-value">{{ $num($stats->avg_humidity, 0) }}@if($stats->avg_humidity !== null)<small>%</small>@endif</p>
                <p class="fad-support">Average of the records shown</p>
            </article>
        </section>

        @include('farmer-records._filters', ['placeholder' => 'Search by date, season or source'])

        @if($rainBars->isNotEmpty())
            <section class="frec-panel" aria-labelledby="frecRainTitle">
                <div class="frec-panel-head">
                    <h2 id="frecRainTitle">Rainfall by Record</h2>
                    <p class="fad-support">Millimetres of rain in each record{{ $rainBars->count() >= 12 ? ', latest 12' : '' }}, oldest first.</p>
                </div>
                @if($wettest)<p class="frec-takeaway">The wettest record is {{ $wettest->date_label }}, with {{ $num($wettest->rainfall) }} mm of rain.</p>@endif
                <ol class="frec-bars">
                    @foreach($rainBars as $bar)
                        <li>
                            <span class="frec-bar-label">{{ $bar->date_label }}</span>
                            <span class="frec-bar" role="img" aria-label="{{ $bar->date_label }}: {{ $num($bar->rainfall) }} mm"><i @style(['width: '.($maxRain > 0 ? max(1, round($bar->rainfall / $maxRain * 100)) : 0).'%'])></i></span>
                            <span class="frec-bar-value">{{ $num($bar->rainfall) }} mm</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="frec-panel" aria-labelledby="frecListTitle">
            <div class="frec-panel-head is-row">
                <h2 id="frecListTitle">Records</h2>
                <p class="fad-support">
                    Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}
                    @if($records->total() > 1)
                        · <a href="{{ request()->fullUrlWithQuery(['sort' => $sort === 'newest' ? 'oldest' : null, 'page' => null]) }}">{{ $sort === 'newest' ? 'Show oldest first' : 'Show newest first' }}</a>
                    @endif
                </p>
            </div>
            @if($records->count())
                <div class="frec-table-wrap">
                    {{-- Wide screens only; below 900px of page width the same records are the cards under this table. --}}
                    <table class="frec-table">
                        <thead>
                            <tr>
                                <th scope="col">Date</th>
                                <th scope="col" class="is-num">Rainfall (mm)</th>
                                <th scope="col" class="is-num">Temperature (°C)</th>
                                <th scope="col" class="is-num">Humidity (%)</th>
                                <th scope="col">Season</th>
                                <th scope="col">Source</th>
                                <th scope="col" class="is-end"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr>
                                    <th scope="row">{{ $record->date_label }}</th>
                                    <td class="is-num">{{ $num($record->rainfall) }}</td>
                                    <td class="is-num">{{ $num($record->temperature) }}</td>
                                    <td class="is-num">{{ $num($record->humidity, 0) }}</td>
                                    <td>@if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@else — @endif</td>
                                    <td @if($record->source !== $record->source_label) title="Source: {{ $record->source }}" @endif>{{ $record->source_label ?: '—' }}</td>
                                    <td class="is-end">@if($record->id)<a class="fad-btn is-quiet is-compact" href="{{ route('climate-records.show', $record->id) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View record for {{ $record->date_label }}">View</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="frec-cards">
                    @foreach($records as $record)
                        <li class="frec-card">
                            <div class="frec-card-head">
                                <h3>{{ $record->date_label }}</h3>
                                @if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@endif
                            </div>
                            <dl class="frec-facts">
                                <div><dt>Rainfall</dt><dd>@if($record->rainfall !== null){{ $num($record->rainfall) }} mm @else — @endif</dd></div>
                                <div><dt>Temperature</dt><dd>@if($record->temperature !== null){{ $num($record->temperature) }} °C @else — @endif</dd></div>
                                <div><dt>Humidity</dt><dd>@if($record->humidity !== null){{ $num($record->humidity, 0) }} % @else — @endif</dd></div>
                                <div><dt>Source</dt><dd @if($record->source !== $record->source_label) title="Source: {{ $record->source }}" @endif>{{ $record->source_label ?: '—' }}</dd></div>
                            </dl>
                            @if($record->id)<a class="fad-btn is-quiet" href="{{ route('climate-records.show', $record->id) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog">View Record</a>@endif
                        </li>
                    @endforeach
                </ul>
                @if($records->hasPages())<div class="fad-pages">{{ $records->links() }}</div>@endif
            @else
                {{-- Three different situations, never mixed up: a read failure, an empty store, or filters that match nothing. --}}
                <div class="fad-empty">
                    @if($totalRecords === 0 && $unavailable)
                        <p class="fad-lead">Climate records are temporarily unavailable.</p>
                        <p class="fad-body">Please try again later.</p>
                    @elseif($totalRecords === 0)
                        <p class="fad-lead">No climate records found.</p>
                        <p class="fad-body">No climate records have been stored yet.</p>
                    @else
                        <p class="fad-lead">No records match your current filters.</p>
                        <p class="fad-body">Try changing the search or filters.</p>
                        <a class="fad-btn is-quiet" href="{{ route('climate-records.index') }}">Reset Filters</a>
                    @endif
                </div>
            @endif
        </section>

        <section class="frec-note" aria-labelledby="frecSourceTitle">
            <h2 id="frecSourceTitle" class="fad-label">About These Records</h2>
            @php
                $hasAmbulong = $sources->contains(\App\Http\Controllers\ClimateRecordController::AMBULONG_SOURCE);
                $otherSources = $sources->reject(fn ($s) => $s === \App\Http\Controllers\ClimateRecordController::AMBULONG_SOURCE)->values();
            @endphp
            @if($hasAmbulong)
                <p class="fad-support">iClimate uses these past weather records for Lian, Batangas. Each record is one month. Temperature is the average of the month's highs and lows.</p>
                <p class="frec-reference"><span class="fad-label">About These Records</span> <strong>Lian, Batangas Climate Records</strong></p>
                <p class="frec-provenance">{{ \App\Services\Climate\ClimateRecordHistory::AMBULONG_PROVENANCE_NOTE }}</p>
            @endif
            @if($otherSources->isNotEmpty())
                <p class="fad-support">{{ $hasAmbulong ? 'Other records come from' : 'Source:' }} {{ $otherSources->join(', ') }}.</p>
            @endif
            <p class="fad-support">These records are not tied to one farm. Wind speed is not shown because the records do not state its unit.</p>
            @if($unavailable && $totalRecords > 0)
                <p class="fad-support">Some climate records are temporarily unavailable ({{ implode(', ', $unavailable) }}), so they are not included.</p>
            @endif
        </section>
    </div>

    {{-- "View" opens the record here, in the same pop-up as the Advisories page (farmer-advisories.js); the list, its
         filters and the scroll position stay as they are. Without JavaScript the link opens the record's own page. --}}
    @include('partials.fad-modal-shell', ['label' => 'Climate Record', 'thing' => 'climate record'])
</x-app-layout>
