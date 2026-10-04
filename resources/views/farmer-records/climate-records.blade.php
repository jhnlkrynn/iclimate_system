<x-app-layout>
    @php
        $num = fn ($value, int $decimals = 1) => $value === null ? '—' : number_format((float) $value, $decimals);
        $day = fn ($date) => $date ? \Illuminate\Support\Carbon::parse($date)->format('M j, Y') : '—';
        $maxRain = $rainBars->max('rainfall') ?: 0;
        $wettest = $rainBars->sortByDesc('rainfall')->first();
    @endphp

    <div class="fad frec">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Climate Records</span>
                <h1>Climate Records</h1>
                <p>Review historical climate information recorded for Lian, Batangas.</p>
            </div>
        </header>

        <section class="frec-kpis" aria-label="Summary of the records shown">
            <article class="frec-kpi">
                <h2 class="fad-label">Records</h2>
                <p class="frec-kpi-value">{{ number_format((int) $stats->records) }}</p>
                <p class="fad-support">{{ $stats->records ? $day($stats->first_date).' – '.$day($stats->last_date) : 'No records match' }}</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Average Temperature</h2>
                <p class="frec-kpi-value">{{ $num($stats->avg_temp) }}@if($stats->avg_temp !== null)<small>°C</small>@endif</p>
                <p class="fad-support">Highest {{ $num($stats->max_temp) }}°C · lowest {{ $num($stats->min_temp) }}°C</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Total Rainfall</h2>
                <p class="frec-kpi-value">{{ $num($stats->total_rain) }}@if($stats->total_rain !== null)<small>mm</small>@endif</p>
                <p class="fad-support">Added up across the records shown</p>
            </article>
            <article class="frec-kpi">
                <h2 class="fad-label">Average Humidity</h2>
                <p class="frec-kpi-value">{{ $num($stats->avg_humidity) }}@if($stats->avg_humidity !== null)<small>%</small>@endif</p>
                <p class="fad-support">Relative humidity</p>
            </article>
        </section>

        @include('farmer-records._filters', ['placeholder' => 'Search by date, season or source'])

        @if($rainBars->isNotEmpty())
            <section class="frec-panel" aria-labelledby="frecRainTitle">
                <div class="frec-panel-head">
                    <h2 id="frecRainTitle">Rainfall by Record</h2>
                    <p class="fad-support">Millimetres of rain in each record{{ $rainBars->count() >= 12 ? ', latest 12' : '' }}, oldest first.</p>
                </div>
                @if($wettest)<p class="frec-takeaway">The wettest record is {{ $day($wettest->record_date) }}, with {{ $num($wettest->rainfall) }} mm of rain.</p>@endif
                <ol class="frec-bars">
                    @foreach($rainBars as $bar)
                        <li>
                            <span class="frec-bar-label">{{ $day($bar->record_date) }}</span>
                            <span class="frec-bar" role="img" aria-label="{{ $day($bar->record_date) }}: {{ $num($bar->rainfall) }} mm"><i @style(['width: '.($maxRain > 0 ? max(1, round($bar->rainfall / $maxRain * 100)) : 0).'%'])></i></span>
                            <span class="frec-bar-value">{{ $num($bar->rainfall) }} mm</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif

        <section class="frec-panel" aria-labelledby="frecListTitle">
            <div class="frec-panel-head is-row">
                <h2 id="frecListTitle">Records</h2>
                <p class="fad-support">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</p>
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
                                <th scope="col" class="is-num">Wind Speed*</th>
                                <th scope="col">Season</th>
                                <th scope="col">Source</th>
                                <th scope="col" class="is-end"><span class="visually-hidden">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($records as $record)
                                <tr>
                                    <th scope="row">{{ $day($record->record_date) }}</th>
                                    <td class="is-num">{{ $num($record->rainfall, 2) }}</td>
                                    <td class="is-num">{{ $num($record->temperature, 2) }}</td>
                                    <td class="is-num">{{ $num($record->humidity, 2) }}</td>
                                    <td class="is-num">{{ $num($record->wind_speed, 2) }}</td>
                                    <td>@if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@else — @endif</td>
                                    <td>{{ $record->source ?: '—' }}</td>
                                    <td class="is-end"><a class="fad-btn is-quiet is-compact" href="{{ route('climate-records.show', $record) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View record for {{ $day($record->record_date) }}">View</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <ul class="frec-cards">
                    @foreach($records as $record)
                        <li class="frec-card">
                            <div class="frec-card-head">
                                <h3>{{ $day($record->record_date) }}</h3>
                                @if($record->season)<span class="fad-badge {{ $record->season === 'Wet' ? 'is-info' : 'is-watch' }}">{{ $record->season }}</span>@endif
                            </div>
                            <dl class="frec-facts">
                                <div><dt>Rainfall</dt><dd>{{ $num($record->rainfall, 2) }} mm</dd></div>
                                <div><dt>Temperature</dt><dd>{{ $num($record->temperature, 2) }} °C</dd></div>
                                <div><dt>Humidity</dt><dd>{{ $num($record->humidity, 2) }} %</dd></div>
                                <div><dt>Wind speed*</dt><dd>{{ $num($record->wind_speed, 2) }}</dd></div>
                                <div><dt>Source</dt><dd>{{ $record->source ?: '—' }}</dd></div>
                            </dl>
                            <a class="fad-btn is-quiet" href="{{ route('climate-records.show', $record) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View record for {{ $day($record->record_date) }}">View Record</a>
                        </li>
                    @endforeach
                </ul>
                @if($records->hasPages())<div class="fad-pages">{{ $records->links() }}</div>@endif
            @else
                <div class="fad-empty">
                    <p class="fad-lead">No climate records found.</p>
                    <p class="fad-body">Try changing the search or filters.</p>
                    <a class="fad-btn is-quiet" href="{{ route('climate-records.index') }}">Reset Filters</a>
                </div>
            @endif
        </section>

        <section class="frec-note" aria-labelledby="frecSourceTitle">
            <h2 id="frecSourceTitle" class="fad-label">About these records</h2>
            <p class="fad-support">Source: {{ $sources->isNotEmpty() ? $sources->join(', ') : 'not recorded' }}. These records are not tied to one farm.</p>
            <p class="fad-support">* Wind speed is shown as recorded; the records do not state its unit.</p>
        </section>
    </div>

    {{-- "View" opens the record here, in the same pop-up as the Advisories page (farmer-advisories.js); the list, its
         filters and the scroll position stay as they are. Without JavaScript the link opens the record's own page. --}}
    @include('partials.fad-modal-shell', ['label' => 'Climate Record', 'thing' => 'climate record'])
</x-app-layout>
