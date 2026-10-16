<section class="hmx-view" data-heat-map-detail="{{ $key }}:{{ $b['barangay'] }}">
    <button type="button" class="hmx-back" data-heat-map-pick="">&larr; {{ $t('back') }}</button>
    <h2 class="hmx-title">{{ $b['name'] }}</h2>
    {{-- Manuscript – Naicel: a layer (flood / drought / yield) shows its own level name and title. --}}
    <p class="hmx-badge is-{{ $b['level'] }}" data-heat-map-level>{{ $b['level'] === 'nodata' || ($r['layer'] ?? false) ? $b['label'] : $t('risk_of', ['level' => $b['label']]) }}</p>
    <p class="hmx-when">{{ ($r['layer'] ?? false) ? $r['title'] : $t($key === 'week' ? 'this_week' : 'two_weeks') }}</p>

    @if (! ($r['layer'] ?? false) && (! empty($b['factor']) || ! empty($b['impact'])))
        <dl class="hmx-factor" data-heat-map-factor>
            @if (! empty($b['factor']))<div><dt>{{ $t('factor_title') }}</dt><dd>{{ $b['factor'] }}</dd></div>@endif
            @if (! empty($b['impact']))<div><dt>{{ $t('impact_title') }}</dt><dd>{{ $b['impact'] }}</dd></div>@endif
        </dl>
    @endif

    <h3 class="hmx-sub">{{ $t('meaning_title') }}</h3>
    <p class="hmx-text" data-heat-map-meaning>{{ $b['meaning'] }}</p>
    @foreach ($b['also'] as $also)
        <p class="hmx-text">{{ $also }}</p>
    @endforeach

    <h3 class="hmx-sub">{{ $t('action_title') }}</h3>
    <p class="hmx-text hmx-action" data-heat-map-action>{{ $b['action'] }}</p>

    @if ($showNumbers && $b['numbers'] !== [])
        <h3 class="hmx-sub">{{ $t('numbers_title') }}</h3>
        <dl class="hmx-numbers" data-heat-map-numbers>
            @foreach ($b['numbers'] as $label => $value)
                <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
            @endforeach
        </dl>
    @endif
    @if ($b['partial'])
        <p class="hmx-source">{{ $b['partial'] }}</p>
    @endif
    <x-updated-at :at="$r['updated_at']" :label="trans('farmer.home.updated', [], $locale)" />
    @if ($r['delayed'] && $b['level'] !== 'nodata')
        <p class="hmx-source">{{ $t('delayed') }}</p>
    @endif
</section>
