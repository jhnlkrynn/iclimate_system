<x-app-layout>
    <x-slot:title>{{ __('yield_request.title') }}</x-slot:title>
    @vite('resources/css/pages/weather-predictions.css')
    @php
        $Y = fn (string $key, array $replace = []) => __('yield_request.'.$key, $replace);
        $num = fn ($v, int $dec = 2) => rtrim(rtrim(number_format((float) $v, $dec), '0'), '.');
        $areaFmt = fn ($v) => number_format((float) $v, 2);
        $pending = $requests->contains(fn ($r) => $r->status === \App\Models\YieldPredictionRequest::QUEUED && $r->created_at?->gt(now()->subMinutes(3)));
        $levels = __('yield_request.impact_levels');
        $predictionLabels = ['requested' => $Y('preparing'), 'processing' => $Y('preparing'), 'completed' => 'Completed', 'failed' => $Y('failed')];
        $tz = \App\Support\BusinessClock::timezone();
        $showsEstimate = \App\Services\Prediction\YieldModelGate::showsStoredEstimate();

        $farmData = $farms->values()->map(function ($f, $idx) use ($Y, $areaFmt) {
            $name = $f->farm_name ?: ('Farm '.($idx + 1));
            $brgy = \App\Services\Risk\HeatMapPanel::displayName((string) $f->barangay);
            $type = in_array($f->farm_type, ['Irrigated', 'Rainfed'], true) ? $f->farm_type : 'Irrigated';
            $area = $f->modelArea();

            return [
                'id' => $f->id,
                'label' => $name.' · '.$brgy,
                'summary' => $brgy.' · '.$Y(strtolower($type)).' · '.($area !== null ? $areaFmt($area).' ha' : $Y('size_missing')),
                'has_size' => $area !== null,
                'edit_url' => route('farmer.farms.edit', $f),
            ];
        })->values();

        $activeFarmData = $farmData->firstWhere('id', $selectedFarm?->id) ?? $farmData->first();
        $latest = $requests->first();
        $seasonHeading = function ($r) use ($Y) {
            $year = $r->target_month?->year ?? (int) now(\App\Support\BusinessClock::timezone())->year;
            $seasonLabel = $r->season === \App\Support\RiceSeason::DRY
                ? $Y('season_short_dry').' '.($year - 1).'–'.$year
                : $Y('season_short_wet').' '.$year;
            $farmName = $r->farm?->farm_name ?: 'Farm';
            $brgy = \App\Services\Risk\HeatMapPanel::displayName((string) ($r->farm?->barangay ?? $r->barangay));

            return $seasonLabel.' · '.$farmName.' ('.$brgy.')';
        };
    @endphp
    <div class="fad wpx is-farmer" data-yield-prediction data-prediction-realtime data-prediction-labels='@json($predictionLabels)' @if ($pending) data-yield-pending @endif>
        @include('partials.manuscript-tabs', ['group' => 'analysis', 'label' => 'Predictions and analysis'])
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $Y('kicker') }}</span>
                <h1>{{ $Y('title') }}</h1>
                <p>{{ $Y('intro') }}</p>
            </div>
        </header>

        <form class="fad-card mb-3" method="POST" action="{{ route('analysis.yield.store') }}" data-loading="true" data-yield-form data-farms='@json($farmData->keyBy("id"))'>
            @csrf
            <h2 class="h5 fw-bold mb-3">{{ $Y('form_title') }}</h2>

            @if ($farms->isEmpty())
                <p class="mb-2">{{ $Y('no_farm') }}</p>
                <a class="btn btn-primary" href="{{ route('farmer.farms.create') }}">{{ $Y('add_farm') }}</a>
            @else
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" @if ($farms->count() >= 2) for="ypFarm" @endif>{{ $Y('farm') }}</label>
                        @if ($farms->count() === 1)
                            <div class="yp-farm-plain" data-single-farm>{{ $activeFarmData['label'] }}</div>
                            <input type="hidden" name="farm_id" value="{{ $activeFarmData['id'] }}">
                        @else
                            <select class="form-select" id="ypFarm" name="farm_id" data-yield-farm-select>
                                @foreach ($farmData as $fd)
                                    <option value="{{ $fd['id'] }}" @selected((int) old('farm_id', $activeFarmData['id']) === (int) $fd['id'])>{{ $fd['label'] }}</option>
                                @endforeach
                            </select>
                        @endif
                        <div class="yp-farm-meta mt-2">
                            <span class="text-muted small" data-farm-summary>{{ $activeFarmData['summary'] }}</span>
                            <a class="small fw-semibold ms-2" href="{{ $activeFarmData['edit_url'] }}" data-farm-edit-link>{{ $Y('edit_farm') }}</a>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold" for="ypSeason">{{ $Y('season') }}</label>
                        <select class="form-select" id="ypSeason" name="season">
                            @foreach (['Wet' => 'wet', 'Dry' => 'dry'] as $value => $key)
                                <option value="{{ $value }}" @selected(old('season', $defaults['season']) === $value)>{{ $Y($key) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12" data-missing-size-wrap @if ($activeFarmData['has_size']) hidden @endif>
                        <div class="yp-missing-size">
                            <label class="form-label fw-semibold" for="ypArea">{{ $Y('area') }}</label>
                            <input class="form-control yp-area-input" id="ypArea" name="area_hectares" type="number" step="0.01" min="0.01" max="1000" value="{{ old('area_hectares') }}" @if (! $activeFarmData['has_size']) required @else disabled @endif>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="ypSaveSize" name="save_size_to_farm" value="1" @checked(old('save_size_to_farm', '1')) @if ($activeFarmData['has_size']) disabled @endif>
                                <label class="form-check-label small" for="ypSaveSize">{{ $Y('save_to_farm') }}</label>
                            </div>
                        </div>
                    </div>
                </div>

                @if ($errors->any())<div class="text-danger small mt-2">{{ $errors->first() }}</div>@endif
                <button class="btn btn-primary mt-3" type="submit" data-loading-text="...">{{ $Y('submit') }}</button>
            @endif
        </form>

        @if ($latest)
            <section class="fad-card mb-3 yp-result-card" aria-labelledby="ypLatest" data-yield-latest data-request-status="{{ $latest->status }}" data-prediction-reference="{{ $latest->id }}" data-prediction-status="{{ $latest->status }}" data-prediction-status-url="{{ route('analysis.yield.status', $latest->id) }}">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h2 class="h5 fw-bold mb-0" id="ypLatest">{{ $seasonHeading($latest) }}</h2>
                    <span class="badge text-bg-secondary" data-prediction-live-status>{{ $latest->status === 'done' ? 'Completed' : ($latest->status === 'failed' ? $Y('failed') : $Y('preparing')) }}</span>
                </div>

                @if ($latest->status === 'done' && ! $showsEstimate)
                    <p class="mb-1" data-ml-status="withheld">{{ __('farmer.home.harvest.unvalidated') }}</p>
                    <p class="small text-muted mb-0">{{ $Y('early_note') }}</p>
                @elseif ($latest->status === 'done')
                    @php
                        $perHa = (float) $latest->predicted_yield;
                        $perHaSacks = number_format((int) round($perHa * 20));
                        $wholeTons = $latest->total_tons !== null ? (int) round((float) $latest->total_tons) : null;
                        $wholeSacks = $latest->total_tons !== null ? number_format((int) round((float) $latest->total_tons * 20)) : null;
                        $compareKey = match ($latest->impact_level) {
                            'Favorable' => 'compare_above',
                            'At Risk' => 'compare_below',
                            default => 'compare_close',
                        };
                    @endphp
                    <div class="yp-metrics">
                        <div class="yp-metric">
                            <p class="yp-metric-main">{{ $Y('about_per_ha', ['value' => $num($perHa)]) }}</p>
                            <p class="yp-metric-sacks">{{ $Y('sacks_note', ['sacks' => $perHaSacks]) }}</p>
                        </div>
                        @if ($wholeTons !== null)
                            <div class="yp-metric">
                                <p class="yp-metric-main">{{ $Y('about_whole_farm', ['value' => number_format($wholeTons)]) }}</p>
                                <p class="yp-metric-sacks">{{ $Y('sacks_note', ['sacks' => $wholeSacks]) }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="d-flex flex-wrap align-items-center gap-2 mt-3">
                        @if ($latest->historical_average !== null)
                            <span class="fw-semibold">{{ $Y($compareKey, ['value' => number_format((float) $latest->historical_average, 2)]) }}</span>
                        @endif
                        @if ($latest->impact_level)
                            <span @class(['badge', 'text-bg-success' => $latest->impact_level === 'Favorable', 'text-bg-secondary' => $latest->impact_level === 'Normal', 'text-bg-danger' => $latest->impact_level === 'At Risk'])>{{ $levels[$latest->impact_level] ?? $latest->impact_level }}</span>
                        @endif
                    </div>

                    @php
                        $isFallback = (bool) data_get($latest->details, 'fallback', false);
                        $factor = (float) data_get($latest->details, 'factor', 1.0);
                        $isAdjusted = abs($factor - 1.0) >= 0.0001;
                        $modelValue = data_get($latest->details, 'model_value');
                    @endphp
                    <p class="small text-muted mt-2 mb-1">{{ $Y('early_note') }}</p>
                    <p class="small text-muted mb-1" data-yield-basis>{{ $Y($isFallback ? 'basis_fallback' : 'basis_forecast') }}</p>
                    @if ($isAdjusted && $latest->barangay)
                        <p class="small fw-semibold mb-1" data-yield-adjusted>{{ $Y('adjusted_for_farm', ['barangay' => $latest->barangay, 'farm_type' => strtolower($Y(strtolower($latest->farm_type))), 'value' => $num($perHa)]) }}</p>
                        @if ($modelValue !== null)
                            <p class="small text-muted mb-3" data-yield-model-value>{{ $Y('model_value_note', ['value' => $num($modelValue)]) }}</p>
                        @endif
                    @else
                        <p class="small text-muted mb-3" data-yield-lian-wide>{{ $Y('lian_wide_note') }}</p>
                    @endif

                    <div class="yp-useful pt-2 border-top d-flex flex-wrap align-items-center gap-2">
                        <span class="small fw-semibold text-muted">{{ $Y('useful_question') }}</span>
                        <form method="POST" action="{{ route('analysis.yield.useful', $latest->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="useful" value="1">
                            <button type="submit" @class(['btn btn-sm', 'btn-success' => $latest->useful === true, 'btn-outline-secondary' => $latest->useful !== true])>{{ $Y('yes') }}</button>
                        </form>
                        <form method="POST" action="{{ route('analysis.yield.useful', $latest->id) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="useful" value="0">
                            <button type="submit" @class(['btn btn-sm', 'btn-secondary' => $latest->useful === false, 'btn-outline-secondary' => $latest->useful !== false])>{{ $Y('no') }}</button>
                        </form>
                    </div>
                @elseif (in_array($latest->status, ['queued', 'processing'], true))
                    <p class="text-muted mb-0">{{ $Y('preparing') }}</p>
                @else
                    <p class="text-muted mb-0">{{ $Y('failed') }}@if ($latest->error) — {{ $latest->error }}@endif</p>
                @endif
            </section>
        @endif

        <section class="fad-card" aria-labelledby="ypHistory" data-yield-history>
            <h2 class="h5 fw-bold mb-3" id="ypHistory">{{ $Y('history') }}</h2>
            @if ($requests->isEmpty())
                <p class="mb-0">{{ $Y('none') }}</p>
            @else
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>{{ $Y('season_farm') }}</th>
                                <th>{{ $Y('per_ha') }}</th>
                                <th>{{ $Y('total') }}</th>
                                <th>{{ $Y('impact') }}</th>
                                <th>{{ $Y('date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $r)
                                <tr data-request-status="{{ $r->status }}" data-prediction-reference="{{ $r->id }}" data-prediction-status="{{ $r->status }}" data-prediction-status-url="{{ route('analysis.yield.status', $r->id) }}">
                                    <td>
                                        <strong>{{ $seasonHeading($r) }}</strong>
                                        <div class="small text-muted">{{ $Y(strtolower($r->farm_type)) }} · {{ $areaFmt($r->area_hectares) }} ha</div>
                                    </td>
                                    @if ($r->status === 'done' && ! $showsEstimate)
                                        <td colspan="2" data-ml-status="withheld">{{ __('farmer.home.harvest.unvalidated') }}</td>
                                        <td>—</td>
                                    @elseif ($r->status === 'done')
                                        <td>{{ $Y('t_ha', ['value' => $num($r->predicted_yield)]) }}</td>
                                        <td>{{ $r->total_tons !== null ? $Y('tons', ['value' => number_format((int) round((float) $r->total_tons))]) : '—' }}</td>
                                        <td>@if ($r->impact_level)<span @class(['badge', 'text-bg-success' => $r->impact_level === 'Favorable', 'text-bg-secondary' => $r->impact_level === 'Normal', 'text-bg-danger' => $r->impact_level === 'At Risk'])>{{ $levels[$r->impact_level] ?? $r->impact_level }}</span>@else — @endif</td>
                                    @elseif (in_array($r->status, ['queued', 'processing'], true))
                                        <td colspan="3" class="text-muted" data-prediction-live-status>{{ $Y('preparing') }}</td>
                                    @else
                                        <td colspan="3" class="text-muted">{{ $Y('failed') }}<div class="small">{{ $r->error }}</div></td>
                                    @endif
                                    <td class="small text-muted text-nowrap">{{ $r->created_at?->timezone($tz)->format('M j, Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="d-md-none yp-mobile-list">
                    @foreach ($requests as $r)
                        <article class="yp-mobile-card" data-request-status="{{ $r->status }}">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <strong class="small">{{ $seasonHeading($r) }}</strong>
                                <span class="small text-muted text-nowrap">{{ $r->created_at?->timezone($tz)->format('M j, Y') }}</span>
                            </div>
                            <div class="small text-muted mb-2">{{ $Y(strtolower($r->farm_type)) }} · {{ $areaFmt($r->area_hectares) }} ha</div>
                            @if ($r->status === 'done' && ! $showsEstimate)
                                <div class="small" data-ml-status="withheld">{{ __('farmer.home.harvest.unvalidated') }}</div>
                            @elseif ($r->status === 'done')
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 small">
                                    <span>{{ $Y('per_ha') }}: <strong>{{ $Y('t_ha', ['value' => $num($r->predicted_yield)]) }}</strong></span>
                                    <span>{{ $Y('total') }}: <strong>{{ $r->total_tons !== null ? $Y('tons', ['value' => number_format((int) round((float) $r->total_tons))]) : '—' }}</strong></span>
                                    @if ($r->impact_level)<span @class(['badge', 'text-bg-success' => $r->impact_level === 'Favorable', 'text-bg-secondary' => $r->impact_level === 'Normal', 'text-bg-danger' => $r->impact_level === 'At Risk'])>{{ $levels[$r->impact_level] ?? $r->impact_level }}</span>@endif
                                </div>
                            @elseif (in_array($r->status, ['queued', 'processing'], true))
                                <div class="small text-muted">{{ $Y('preparing') }}</div>
                            @else
                                <div class="small text-muted">{{ $Y('failed') }}@if ($r->error) — {{ $r->error }}@endif</div>
                            @endif
                        </article>
                    @endforeach
                </div>
                <p class="small text-muted mt-3 mb-0">{{ $Y('note') }}</p>
            @endif
        </section>
    </div>
</x-app-layout>
