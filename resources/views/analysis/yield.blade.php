<x-app-layout>
    <x-slot:title>{{ __('yield_request.title') }}</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    {{-- Manuscript – Naicel (2026-10-08), Phase 5: YieldPredictionController::farmer. A tab of Forecast, in the farmer's
         language. A request is saved at once; the result fills in after the model runs (the page re-reads itself). --}}
    @php
        $Y = fn (string $key, array $replace = []) => __('yield_request.'.$key, $replace);
        $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2), '0'), '.');
        // Re-read the page only for a request made in the last 3 minutes (a stuck one never reloads the page forever).
        $pending = $requests->contains(fn ($r) => $r->status === \App\Models\YieldPredictionRequest::QUEUED && $r->created_at?->gt(now()->subMinutes(3)));
        $levels = __('yield_request.impact_levels');
    @endphp
    <div class="fad wpx" data-yield-prediction @if ($pending) data-yield-pending @endif>
        @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $Y('kicker') }}</span>
                <h1>{{ $Y('title') }}</h1>
                <p>{{ $Y('intro') }}</p>
            </div>
        </header>

        <form class="fad-card mb-3" method="POST" action="{{ route('analysis.yield.store') }}" data-loading="true">
            @csrf
            <h2 class="h5 fw-bold">{{ $Y('form_title') }}</h2>
            <div class="row g-2">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="ypSeason">{{ $Y('season') }}</label>
                    <select class="form-select" id="ypSeason" name="season">
                        @foreach (['Wet' => 'wet', 'Dry' => 'dry'] as $value => $key)<option value="{{ $value }}" @selected(old('season', $defaults['season']) === $value)>{{ $Y($key) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="ypType">{{ $Y('farm_type') }}</label>
                    <select class="form-select" id="ypType" name="farm_type">
                        @foreach (['Irrigated' => 'irrigated', 'Rainfed' => 'rainfed'] as $value => $key)<option value="{{ $value }}" @selected(old('farm_type', $defaults['farm_type']) === $value)>{{ $Y($key) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="ypBarangay">{{ $Y('barangay') }}</label>
                    <select class="form-select" id="ypBarangay" name="barangay" required>
                        <option value=""></option>
                        @foreach ($barangays as $name)<option value="{{ $name }}" @selected(old('barangay', $defaults['barangay']) === $name)>{{ \App\Services\Risk\HeatMapPanel::displayName($name) }}</option>@endforeach
                    </select>
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold" for="ypArea">{{ $Y('area') }}</label>
                    <input class="form-control" id="ypArea" name="area_hectares" type="number" step="0.01" min="0.01" max="1000" required value="{{ old('area_hectares', $defaults['area']) }}">
                </div>
            </div>
            @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
            <button class="btn btn-primary mt-3" type="submit" data-loading-text="...">{{ $Y('submit') }}</button>
        </form>

        <section class="fad-card" aria-labelledby="ypHistory" data-yield-history>
            <h2 class="h5 fw-bold" id="ypHistory">{{ $Y('history') }}</h2>
            @if ($requests->isEmpty())
                <p class="mb-0">{{ $Y('none') }}</p>
            @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>{{ $Y('for') }}</th><th>{{ $Y('per_ha') }}</th><th>{{ $Y('total') }}</th><th>{{ $Y('usual') }}</th><th>{{ $Y('impact') }}</th></tr></thead>
                        <tbody>
                            @foreach ($requests as $r)
                                <tr data-request-status="{{ $r->status }}">
                                    <td><strong>{{ $Y(strtolower($r->season)) }}</strong>
                                        <div class="small text-muted">{{ \App\Services\Risk\HeatMapPanel::displayName((string) $r->barangay) }} · {{ $Y(strtolower($r->farm_type)) }} · {{ $num($r->area_hectares) }} ha · {{ $Y('requested') }} {{ $r->created_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</div>
                                    </td>
                                    @if ($r->status === 'done')
                                        <td>{{ $Y('t_ha', ['value' => $num($r->predicted_yield)]) }}</td>
                                        <td>{{ $r->total_tons !== null ? $Y('tons', ['value' => $num($r->total_tons)]) : '—' }}</td>
                                        <td>{{ $r->historical_average !== null ? $Y('t_ha', ['value' => $num($r->historical_average)]) : '—' }}</td>
                                        <td>@if ($r->impact_level)<span @class(['badge', 'text-bg-success' => $r->impact_level === 'Favorable', 'text-bg-secondary' => $r->impact_level === 'Normal', 'text-bg-danger' => $r->impact_level === 'At Risk'])>{{ $levels[$r->impact_level] ?? $r->impact_level }}</span>@else — @endif</td>
                                    @elseif ($r->status === 'queued')
                                        <td colspan="4" class="text-muted">{{ $Y('preparing') }}</td>
                                    @else
                                        <td colspan="4" class="text-muted">{{ $Y('failed') }}<div class="small">{{ $r->error }}</div></td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="small text-muted mt-2 mb-0">{{ $Y('impact_help') }} {{ $Y('note') }}</p>
            @endif
        </section>
    </div>
    @if ($pending)
        {{-- A prediction is still being prepared: read the page again shortly (at most a minute). --}}
        <script>
            setTimeout(() => window.location.reload(), 8000);
        </script>
    @endif
</x-app-layout>
