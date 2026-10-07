<x-app-layout>
    <x-slot:title>Yield Forecast</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    {{-- Manuscript – Naicel (2026-10-08), Phase 5: YieldPredictionController::mao. A tab of Predictions. Run a forecast
         for a barangay or all of Lian; the model runs after the response and the row fills in. --}}
    @php
        $num = fn ($v, string $unit = '') => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2), '0'), '.').$unit;
        // Re-read the page only for a forecast queued in the last 3 minutes (a stuck one never reloads the page forever).
        $pending = $requests->contains(fn ($r) => $r->status === \App\Models\YieldPredictionRequest::QUEUED && $r->created_at?->gt(now()->subMinutes(3)));
    @endphp
    <div class="mfd itd itp">
        <x-it.page-header kicker="Weather and Rice Yield" title="Yield Forecast" lede="Forecast rice yield per barangay or for the whole municipality, by season and irrigation, and compare past forecasts with actual harvests." />
        <div class="fad wpx" data-yield-forecast @if ($pending) data-yield-pending @endif>
            @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])

            <form class="fad-card mb-3" method="POST" action="{{ route('analysis.forecast.store') }}" data-loading="true">
                @csrf
                <h2 class="h5 fw-bold">Run a forecast</h2>
                <div class="row g-2 align-items-end">
                    <div class="col-md-3"><label class="form-label fw-semibold" for="yfScope">For</label>
                        <select class="form-select" id="yfScope" name="scope">
                            <option value="municipal" @selected(old('scope') !== 'barangay')>All of Lian</option>
                            <option value="barangay" @selected(old('scope') === 'barangay')>One barangay</option>
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label fw-semibold" for="yfBarangay">Barangay</label>
                        <select class="form-select" id="yfBarangay" name="barangay"><option value="">—</option>@foreach ($barangays as $b)<option value="{{ $b }}" @selected(old('barangay') === $b)>{{ $b }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2"><label class="form-label fw-semibold" for="yfSeason">Season</label>
                        <select class="form-select" id="yfSeason" name="season"><option value="Wet" @selected(old('season', $season) === 'Wet')>Wet</option><option value="Dry" @selected(old('season', $season) === 'Dry')>Dry</option></select>
                    </div>
                    <div class="col-md-2"><label class="form-label fw-semibold" for="yfType">Irrigation</label>
                        <select class="form-select" id="yfType" name="farm_type"><option>Irrigated</option><option @selected(old('farm_type') === 'Rainfed')>Rainfed</option></select>
                    </div>
                    <div class="col-md-2"><label class="form-label fw-semibold" for="yfArea">Area (ha)</label>
                        <input class="form-control" id="yfArea" name="area_hectares" type="number" step="0.01" min="0.01" value="{{ old('area_hectares') }}" placeholder="From records">
                    </div>
                </div>
                <p class="small text-muted mt-2 mb-0">Leave the area empty to use the rice area on the Barangays page (or what farmers registered){{ $areas ? ': all of Lian '.$num(array_sum($areas), ' ha') : '' }}.</p>
                @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
                <button class="btn btn-primary mt-3" type="submit" data-loading-text="Queuing...">Run forecast</button>
            </form>

            <section class="fad-card mb-3" aria-labelledby="yfHistory" data-forecast-history>
                <h2 class="h5 fw-bold" id="yfHistory">Forecast history</h2>
                @if ($requests->isEmpty())
                    <p class="mb-0">No forecast has been run yet.</p>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead><tr><th>For</th><th>Season</th><th>Area</th><th>Predicted</th><th>Total</th><th>Usual</th><th>Outlook</th><th>Actual (records)</th><th>Run by</th></tr></thead>
                            <tbody>
                                @foreach ($requests as $r)
                                    <tr data-request-status="{{ $r->status }}">
                                        <td>{{ $r->barangay ?? 'All of Lian' }}</td>
                                        <td>{{ $r->season }} {{ $r->target_month?->format('M Y') }} · {{ $r->farm_type }}</td>
                                        <td>{{ $num($r->area_hectares, ' ha') }}</td>
                                        @if ($r->status === 'done')
                                            <td>{{ $num($r->predicted_yield, ' t/ha') }}</td>
                                            <td>{{ $num($r->total_tons, ' t') }}</td>
                                            <td>{{ $num($r->historical_average, ' t/ha') }}</td>
                                            <td>@if ($r->impact_level)<span @class(['badge', 'text-bg-success' => $r->impact_level === 'Favorable', 'text-bg-secondary' => $r->impact_level === 'Normal', 'text-bg-danger' => $r->impact_level === 'At Risk'])>{{ $r->impact_level }}</span>@else — @endif</td>
                                            <td>{{ isset($actuals[$r->id]) ? $num($actuals[$r->id], ' t/ha') : 'Not recorded yet' }}</td>
                                        @elseif ($r->status === 'queued')
                                            <td colspan="5" class="text-muted">Preparing…</td>
                                        @else
                                            <td colspan="5" class="text-muted small">{{ $r->error }}</td>
                                        @endif
                                        <td class="small">{{ $r->user?->name }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="fad-card" aria-labelledby="yfActual" data-predicted-vs-actual>
                <h2 class="h5 fw-bold" id="yfActual">Predicted vs actual (farmers' harvests)</h2>
                @if (($corrections['count'] ?? 0) === 0)
                    <p class="mb-0">No farmer has recorded an actual harvest next to a prediction yet. Farmers answer "What was your real harvest?" on My Farm after harvest.</p>
                @else
                    <p>{{ $corrections['count'] }} harvests · average predicted {{ $num($corrections['predicted'], ' t/ha') }} · average actual {{ $num($corrections['actual'], ' t/ha') }} · average miss (MAE) <strong>{{ $num($corrections['error'], ' t/ha') }}</strong>.</p>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Harvested</th><th>Barangay</th><th>Season</th><th>Predicted</th><th>Actual</th><th>Difference</th></tr></thead>
                            <tbody>
                                @foreach ($corrections['rows']->take(15) as $row)
                                    <tr><td>{{ $row['date'] ?? '—' }}</td><td>{{ $row['barangay'] ?? '—' }}</td><td>{{ $row['season'] ?? '—' }}</td><td>{{ $num($row['predicted'], ' t/ha') }}</td><td>{{ $num($row['actual'], ' t/ha') }}</td><td>{{ ($row['difference'] > 0 ? '+' : '').$num($row['difference'], ' t/ha') }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>
    </div>
    @if ($pending)
        <script>
            setTimeout(() => window.location.reload(), 8000);
        </script>
    @endif
</x-app-layout>
