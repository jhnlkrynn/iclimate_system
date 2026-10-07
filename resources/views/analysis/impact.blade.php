<x-app-layout>
    <x-slot:title>Weather Impact Analysis</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    {{-- Manuscript – Naicel (2026-10-08), Phase 4: WeatherAnalysisController::impact / show. Pearson correlation of each
         climate variable with the municipal rice yield (WeatherAnalysisService::impact), ranked, with an impact level. --}}
    @php
        $badge = fn (?string $level) => match ($level) { 'High' => 'text-bg-danger', 'Moderate' => 'text-bg-warning', 'Low' => 'text-bg-success', default => 'text-bg-light' };
        $strongest = $result['strongest'] ?? null;
    @endphp
    <div class="mfd itd itp">
        <x-it.page-header kicker="Weather and Rice Yield" title="Weather Impact Analysis" lede="Which weather (rainfall, temperature, humidity or wind) moved most with Lian's rice yield, by season and years." />
        <div class="fad wpx" data-impact-analysis>
            @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])

            @if ($saved)
                <div class="alert alert-info" data-saved-analysis>
                    Saved analysis <strong>{{ $saved->title }}</strong> · {{ $saved->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y g:i A') }} by {{ $saved->author?->name ?? 'MAO' }}.
                    @if ($saved->notes)<div class="small mt-1">{{ $saved->notes }}</div>@endif
                    <a class="ms-2" href="{{ route('analysis.impact', array_filter($filters)) }}">Run again with today's data</a>
                </div>
            @else
                <form class="fad-card mb-3" method="GET" action="{{ route('analysis.impact') }}">
                    <div class="row g-2 align-items-end">
                        <div class="col-sm-4"><label class="form-label fw-semibold" for="iaSeason">Season</label>
                            <select class="form-select" id="iaSeason" name="season">
                                <option value="">Both seasons</option>
                                <option value="Wet" @selected($filters['season'] === 'Wet')>Wet (May–Oct)</option>
                                <option value="Dry" @selected($filters['season'] === 'Dry')>Dry (Nov–Apr)</option>
                            </select>
                        </div>
                        <div class="col-sm-3"><label class="form-label fw-semibold" for="iaFrom">From year</label>
                            <select class="form-select" id="iaFrom" name="from_year"><option value="">First</option>@foreach ($years as $y)<option value="{{ $y }}" @selected($filters['from_year'] === $y)>{{ $y }}</option>@endforeach</select>
                        </div>
                        <div class="col-sm-3"><label class="form-label fw-semibold" for="iaTo">To year</label>
                            <select class="form-select" id="iaTo" name="to_year"><option value="">Latest</option>@foreach ($years as $y)<option value="{{ $y }}" @selected($filters['to_year'] === $y)>{{ $y }}</option>@endforeach</select>
                        </div>
                        <div class="col-sm-2 d-grid"><button class="btn btn-primary" type="submit">Run analysis</button></div>
                    </div>
                </form>
            @endif

            <section class="fad-card mb-3" aria-labelledby="iaResultTitle" data-impact-result>
                <h2 class="h5 fw-bold" id="iaResultTitle">Result</h2>
                @if (! $strongest)
                    <p class="mb-0">Not enough months with both a yield record and weather readings ({{ $result['pairs'] }} found; at least {{ \App\Services\Climate\WeatherAnalysisService::MIN_PAIRS }} are needed). Choose more years or both seasons.</p>
                @else
                    <p class="mb-2">Based on <strong>{{ $result['pairs'] }}</strong> months with a yield record ({{ implode(', ', $result['years']) }}).
                        The weather that moved most with yield: <strong>{{ $strongest['label'] }}</strong> ({{ strtolower($strongest['basis_label']) }}),
                        <span class="badge {{ $badge($strongest['impact']) }}" data-strongest-impact>{{ $strongest['impact'] }} impact</span>.</p>
                    <p class="mb-3">{{ $strongest['sentence'] }}</p>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0" data-impact-table>
                            <thead><tr><th>Rank</th><th>Weather</th><th>Period</th><th>Months</th><th>Correlation (r)</th><th>Impact</th><th>Reading</th></tr></thead>
                            <tbody>
                                @foreach ($result['variables'] as $i => $v)
                                    <tr>
                                        <td>{{ $v['r'] === null ? '—' : $i + 1 }}</td>
                                        <td>{{ $v['label'] }}</td>
                                        <td class="small">{{ $v['basis_label'] }}</td>
                                        <td>{{ $v['pairs'] }}</td>
                                        <td>{{ $v['r'] === null ? '—' : number_format($v['r'], 2) }}</td>
                                        <td>@if ($v['impact'])<span class="badge {{ $badge($v['impact']) }}">{{ $v['impact'] }}</span>@else — @endif</td>
                                        <td class="small">{{ $v['sentence'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted mt-2 mb-0">r runs from −1 to 1: the further from 0, the more the two moved together. Impact: High at |r| ≥ {{ \App\Services\Climate\WeatherAnalysisService::HIGH_IMPACT_R }}, Moderate at ≥ {{ \App\Services\Climate\WeatherAnalysisService::MODERATE_IMPACT_R }}, otherwise Low. Moving together does not prove one causes the other.</p>
                @endif
            </section>

            @if (! $saved && $strongest)
                <details class="ic-collapse mb-3">
                    <summary><span class="ic-collapse-title">Save this analysis</span></summary>
                    <form class="fad-card" method="POST" action="{{ route('analysis.impact.store') }}" data-loading="true">
                        @csrf
                        @foreach ($filters as $key => $value)@if ($value !== null)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif @endforeach
                        <label class="form-label fw-semibold" for="iaTitle">Title</label>
                        <input class="form-control" id="iaTitle" name="title" maxlength="150" required value="{{ ($filters['season'] ?? 'Both seasons').' '.($filters['from_year'] ?? ($result['years'][0] ?? '')).'–'.($filters['to_year'] ?? (end($result['years']) ?: '')) }}">
                        <label class="form-label fw-semibold mt-2" for="iaNotes">Notes</label>
                        <textarea class="form-control" id="iaNotes" name="notes" rows="2" maxlength="2000"></textarea>
                        <button class="btn btn-primary mt-2" type="submit" data-loading-text="Saving...">Save</button>
                    </form>
                </details>
            @endif

            <section class="fad-card" aria-labelledby="iaHistoryTitle" data-impact-history>
                <h2 class="h5 fw-bold" id="iaHistoryTitle">Analysis history</h2>
                @if ($history->isEmpty())
                    <p class="mb-0">No saved analysis yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Saved</th><th>Title</th><th>Months</th><th>Strongest</th><th>Impact</th><th>By</th></tr></thead>
                            <tbody>
                                @foreach ($history as $h)
                                    <tr @if ($saved?->is($h)) class="table-active" @endif>
                                        <td class="small">{{ $h->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</td>
                                        <td><a href="{{ route('analysis.impact.show', $h) }}">{{ $h->title }}</a></td>
                                        <td>{{ $h->sample_size }}</td>
                                        <td class="small">{{ $h->strongest_variable ?? '—' }}</td>
                                        <td>@if ($h->impact_level)<span class="badge {{ $badge($h->impact_level) }}">{{ $h->impact_level }}</span>@else — @endif</td>
                                        <td class="small">{{ $h->author?->name ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
