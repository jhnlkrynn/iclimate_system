{{-- Rice Varieties: the planting form's variety, crop establishment and date basis (included by
     planting-schedules/_form). The variety is a plain <select> that resources/js/rice-varieties.js turns into a
     type-to-search box; the preview under the planting date asks rice-varieties.estimate for the expected harvest and,
     while the farmer has not typed their own harvest date, fills it in. --}}
@php
    $varietyId = old('rice_variety_id', $schedule->rice_variety_id);
    $options = \App\Models\RiceVariety::pickerOptions($schedule->rice_variety_id ? (int) $schedule->rice_variety_id : null);
    $establishment = old('crop_establishment', $schedule->crop_establishment);
    $basis = old('date_basis', $schedule->date_basis ?: 'sowing');
    $seedlingAge = old('seedling_age_days', $schedule->seedling_age_days ?: config('rice_varieties.default_seedling_age_days', 21));
    $mode = old('harvest_date_mode', $schedule->harvest_date_manual ? 'manual' : 'auto');
    // Varieties for this farm's type (Irrigated / Rainfed) first, then the rest; newest first in each group. The script
    // regroups the list when "Type of farm" changes.
    $farmType = old('farm_type', $schedule->farm_type);
    $farmType = in_array($farmType, \App\Models\RiceVariety::FARM_TYPES, true) ? $farmType : null;
    $groups = $farmType
        ? [__('rice_varieties.form.suits', ['type' => mb_strtolower(__('rice_varieties.farm_types.'.$farmType))]) => array_filter($options, fn ($o) => in_array($farmType, $o['farm_types'], true)),
           __('rice_varieties.form.other') => array_filter($options, fn ($o) => ! in_array($farmType, $o['farm_types'], true))]
        : [null => $options];
@endphp
<div class="{{ $c['full'] }} rv-plant-fields" data-rv-planting data-rv-estimate-url="{{ route('rice-varieties.estimate') }}">
    <input type="hidden" name="harvest_date_mode" value="{{ $mode }}" data-rv-mode>

    <div class="rv-plant-field" data-rv-picker>
        <label class="{{ $c['label'] }}" for="rice_variety_id">{{ __('rice_varieties.form.variety') }}</label>
        <select class="{{ $c['select'] }}{{ $invalid('rice_variety_id') }}" id="rice_variety_id" name="rice_variety_id" data-rv-select
                data-rv-placeholder="{{ __('rice_varieties.form.choose') }}" aria-describedby="rice_variety_hint"
                data-rv-suits="{{ __('rice_varieties.form.suits', ['type' => ':type']) }}" data-rv-other="{{ __('rice_varieties.form.other') }}"
                data-rv-farm-labels="{{ json_encode(['Irrigated' => mb_strtolower(__('rice_varieties.farm_types.Irrigated')), 'Rainfed' => mb_strtolower(__('rice_varieties.farm_types.Rainfed'))]) }}">
            <option value="">{{ __('rice_varieties.form.dont_know') }}</option>
            @foreach ($groups as $groupLabel => $groupOptions)
                @if ($groupLabel !== '' && $groupOptions)<optgroup label="{{ $groupLabel }}" data-rv-group="{{ $loop->first ? 'suits' : 'other' }}">@endif
                @foreach ($groupOptions as $order => $option)
                    <option value="{{ $option['id'] }}" data-search="{{ $option['search'] }}" data-farm-types="{{ implode(' ', $option['farm_types']) }}" data-order="{{ $order }}" @selected((string) $varietyId === (string) $option['id'])>{{ $option['label'] }}{{ $option['year'] ? ' · '.$option['year'] : '' }}</option>
                @endforeach
                @if ($groupLabel !== '' && $groupOptions)</optgroup>@endif
            @endforeach
        </select>
        @error('rice_variety_id')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        <div class="{{ $c['help'] }}" id="rice_variety_hint">{{ __('rice_varieties.form.variety_hint') }}</div>
    </div>

    <fieldset class="rv-plant-field">
        <legend class="{{ $c['label'] }}">{{ __('rice_varieties.form.establishment') }}</legend>
        <div class="rv-choice-row">
            @foreach (\App\Support\VarietyHarvestEstimator::ESTABLISHMENTS as $value)
                <label class="rv-choice"><input type="radio" name="crop_establishment" value="{{ $value }}" @checked($establishment === $value) data-rv-establishment><span>{{ __('rice_varieties.form.establishments.'.$value) }}</span></label>
            @endforeach
        </div>
        @error('crop_establishment')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
    </fieldset>

    <fieldset class="rv-plant-field" data-rv-basis-wrap>
        <legend class="{{ $c['label'] }}">{{ __('rice_varieties.form.date_basis') }}</legend>
        <div class="rv-segmented" role="radiogroup">
            @foreach (\App\Support\VarietyHarvestEstimator::DATE_BASES as $value)
                <label class="rv-seg"><input type="radio" name="date_basis" value="{{ $value }}" @checked($basis === $value) data-rv-basis><span>{{ __('rice_varieties.form.date_bases.'.$value) }}</span></label>
            @endforeach
        </div>
    </fieldset>

    <div class="rv-plant-field" data-rv-seedling @if ($basis !== 'transplanting') hidden @endif>
        <label class="{{ $c['label'] }}" for="seedling_age_days">{{ __('rice_varieties.form.seedling_age') }}</label>
        <input class="{{ $c['input'] }}{{ $invalid('seedling_age_days') }}" id="seedling_age_days" name="seedling_age_days" type="number" min="5" max="60" step="1" value="{{ $seedlingAge }}" data-rv-seedling-age>
        @error('seedling_age_days')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
    </div>

    @if ($schedule->harvest_date_manual)
        <p class="{{ $c['help'] }}" data-rv-manual-note>{{ __('rice_varieties.form.manual') }}</p>
    @endif
</div>
