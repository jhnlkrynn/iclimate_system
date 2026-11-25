{{-- 4. Expected yield this season + Expected vs Estimated Yield + Last Harvest card. --}}
@php
    $h = $f['harvest'];
    $ey = $f['expected_yield'] ?? [];
    $sc = $ey['season_comparison'] ?? [];
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

    @if (! empty($sc['summary_line']) || ! empty($sc['empty_message']))
        <div class="mt-2" data-season-comparison>
            @if (! empty($sc['summary_line']))
                <p class="small fw-semibold text-dark mb-1">{{ $sc['summary_line'] }}</p>
                @if (! empty($sc['status_label']))
                    @php
                        $badgeClass = match ($sc['status'] ?? 'same') {
                            'higher' => 'bg-success-subtle text-success-emphasis border border-success-subtle',
                            'lower' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                            default => 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle',
                        };
                    @endphp
                    <span class="badge rounded-pill {{ $badgeClass }}" data-season-comparison-status>{{ $sc['status_label'] }}</span>
                @endif
            @elseif (! empty($sc['empty_message']))
                <p class="small text-muted mb-0" data-season-comparison-empty>{{ $sc['empty_message'] }}</p>
            @endif
        </div>
    @endif

    @if (($lastHarvest['yield'] ?? null) !== null)
        <p class="small text-muted mt-2 mb-0" data-expected-last-harvest>{{ $fil ? 'Huling ani' : 'Last harvest' }}: {{ number_format((float) $lastHarvest['yield'], 2) }} t/ha</p>
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
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-1">
            <x-updated-at :at="$h['ran_at']" :label="__('farmer.home.updated')" />
        </div>
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
