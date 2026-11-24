{{-- 4. Expected yield this season + Expected vs Estimated Yield + Last Harvest card. --}}
@php
    $h = $f['harvest'];
    $ey = $f['expected_yield'] ?? [];
    $fil = ($f['locale'] ?? app()->getLocale()) === 'fil';
    $activeFarmId = $f['farm']['id'] ?? ($h['farm_id'] ?? null);
    $lastHarvest = $ey['last_harvest'] ?? null;
@endphp

@if ($h['state'] !== 'ok' && ($h['needs_area'] ?? false))
    @if (is_numeric($h['usual'] ?? null))
    <section class="fh-card" aria-labelledby="fhUsualYieldTitle" data-fh-usual-yield>
        <h2 id="fhUsualYieldTitle" class="fh-kicker">{{ __('farmer.home.harvest.usual_title') }}</h2>
        <p class="fh-muted">{{ $h['season'] }}</p>
        <p class="fh-big">{{ number_format((float) $h['usual'], 2) }} <small>t/ha</small></p>
        <p class="fh-words">{{ __('farmer.home.harvest.usual_note') }}</p>
    </section>
    @endif
@else
<section @class(['fh-card', 'fh-card-opens' => $h['state'] === 'ok']) aria-labelledby="fhHarvestTitle" data-fh-harvest @if ($h['state'] === 'loading') data-mfd-load="harvest" @endif>
    <h2 id="fhHarvestTitle" class="fh-kicker">{{ __('farmer.home.harvest.title') }}</h2>
    <p class="fh-muted">{{ $h['season'] }}</p>

    @if ($h['state'] === 'ok')
        <div class="d-flex flex-wrap align-items-baseline gap-3">
            <div>
                <div class="small text-muted">{{ $fil ? 'Tantiya ng iClimate' : 'iClimate estimate' }}</div>
                <p class="fh-big mb-0">{{ number_format($h['yield'], 2) }} <small>t/ha</small></p>
            </div>
            @if (($ey['expected'] ?? null) !== null)
                <div>
                    <div class="small text-muted">{{ $fil ? 'Inaasahan mo' : 'Your expected' }}</div>
                    <p class="fh-big mb-0" style="font-size: 1.45rem;">{{ number_format((float) $ey['expected'], 2) }} <small>t/ha</small></p>
                </div>
                @if (($ey['diff_t_ha'] ?? null) !== null)
                    @php
                        $sign = $ey['diff_t_ha'] > 0 ? '+' : '';
                        $pctSign = ($ey['diff_pct'] ?? 0) > 0 ? '+' : '';
                    @endphp
                    <div class="small fw-semibold text-dark" data-expected-diff>
                        {{ $sign }}{{ number_format((float) $ey['diff_t_ha'], 2) }} t/ha
                        @if (($ey['diff_pct'] ?? null) !== null) ({{ $pctSign }}{{ $ey['diff_pct'] }}%)@endif
                    </div>
                @endif
            @endif
        </div>
        <p class="fh-muted mt-1">@include('components.unvalidated-estimate')</p>
    @elseif ($h['state'] === 'loading')
        <div class="ic-skel-lines" role="status"><span></span><span></span><span class="visually-hidden">{{ __('farmer.home.harvest.preparing') }}</span></div>
        <p class="fh-muted">{{ __('farmer.home.harvest.preparing') }}</p>
    @else
        <x-no-data :label="__('farmer.home.no_data')" :note="$h['reason']" />
        @if ($h['needs_area'] ?? false)
            @if (! empty($h['farm_id']))
                <a class="fh-link" href="{{ route('farmer.farms.edit', $h['farm_id']) }}">{{ __('farms.yield.add_size') }}</a>
            @else
                <a class="fh-link" href="{{ route('profile.edit') }}">{{ __('farmer.home.harvest.add_area') }}</a>
            @endif
        @endif
    @endif

    @if ($activeFarmId)
        <form method="POST" action="{{ route('farmer.farms.expected-yield', $activeFarmId) }}" class="mt-2 d-flex flex-wrap align-items-end gap-2" data-expected-yield-form>
            @csrf
            @method('PATCH')
            <div>
                <label class="small text-muted d-block mb-1" for="expectedYieldInput">{{ $fil ? 'Inaasahan mong ani (t/ha)' : 'Your expected yield (t/ha)' }}</label>
                <input id="expectedYieldInput" type="number" name="expected_yield_t_ha" step="0.01" min="0.1" max="15"
                    class="form-control form-control-sm" style="width: 140px;"
                    value="{{ old('expected_yield_t_ha', $ey['expected'] ?? '') }}" required>
            </div>
            <button type="submit" class="mfd-btn is-compact">{{ $fil ? 'I-save' : 'Save' }}</button>
            @if (! empty($ey['source_label']))
                <span class="small text-muted" data-expected-source>{{ $ey['source_label'] }}</span>
            @endif
        </form>
    @endif

    @if (! empty($ey['insights']))
        <ul class="mt-2 mb-0 ps-3 small text-muted" data-expected-insights>
            @foreach ($ey['insights'] as $insight)
                <li>{{ $insight }}</li>
            @endforeach
        </ul>
    @endif

    @if ($h['state'] === 'ok')
        @if (! empty($h['size_note']))<p class="fh-muted mt-2 mb-0">{{ $h['size_note'] }}</p>@endif
        <x-updated-at :at="$h['ran_at']" :label="__('farmer.home.updated')" />
        <button type="button" class="fh-card-open" data-panel="expected-harvest" aria-haspopup="dialog" aria-controls="fhDrawer"><span class="mfd-sr-only">{{ __('farmer.kpis.detail.open', ['card' => __('farmer.home.harvest.title')]) }}</span></button>
    @endif
</section>
@endif

@if ($lastHarvest)
<section class="fh-card" aria-labelledby="fhLastHarvestTitle" data-fh-last-harvest>
    <h2 id="fhLastHarvestTitle" class="fh-kicker">{{ $fil ? 'Huling ani' : 'Last harvest' }}</h2>
    <p class="fh-big mb-1">
        {{ $fil ? 'Toneladang naani:' : 'Tons harvested:' }}
        {{ rtrim(rtrim(number_format((float) $lastHarvest['tons'], 2), '0'), '.') }} t
    </p>
    @if ($lastHarvest['has_area'] && $lastHarvest['yield'] !== null && $lastHarvest['area'] !== null)
        <p class="fh-muted mb-0" data-last-harvest-yield>
            {{ $fil ? 'Ani:' : 'Yield:' }}
            {{ rtrim(rtrim(number_format((float) $lastHarvest['yield'], 2), '0'), '.') }} t/ha
            ({{ $fil ? 'mula sa' : 'from' }} {{ rtrim(rtrim(number_format((float) $lastHarvest['area'], 2), '0'), '.') }} ha)
        </p>
    @endif
    @if (! empty($lastHarvest['harvested_on']))
        <p class="small text-muted mt-1 mb-0">{{ $lastHarvest['harvested_on'] }}</p>
    @endif
</section>
@endif
