{{-- Panel revision 1.3: the reason for an early or late harvest. Shown as soon as the chosen harvest date is more than
     the tolerance (config/farm_calendar.php) before or after the expected harvest date; the server checks it again
     (App\Support\HarvestTiming). Used by the planting form and the Activity Calendar dialog.

     $timingWrap  class of the wrapping element         $timingLabel  class of the labels
     $timingInput class of the select / text input       $timingError  class of the error line
     $timingExpected  the expected date (Y-m-d) when it is fixed; otherwise the script reads it from the form
     $timingReason / $timingOther  the saved values --}}
@php
    $tLocale = $timingLocale ?? app()->getLocale();
    $tReasons = collect(\App\Support\HarvestTiming::reasons())->mapWithKeys(fn ($label, $key) => [$key => \App\Support\HarvestTiming::reasonLabel($key, null, $tLocale)]);
    $tReason = old('harvest_timing_reason', $timingReason ?? null);
    $tOther = old('harvest_timing_reason_other', $timingOther ?? null);
    $tErrors = isset($timingBag) ? $errors->getBag($timingBag) : $errors;
@endphp
<div class="{{ $timingWrap ?? '' }} harvest-timing" data-harvest-timing
     data-tolerance="{{ \App\Support\HarvestTiming::toleranceDays() }}"
     @if (! empty($timingExpected)) data-expected="{{ $timingExpected }}" @endif
     data-text-early="{{ __('farmer.harvest_timing.status.early', [], $tLocale) }}"
     data-text-late="{{ __('farmer.harvest_timing.status.late', [], $tLocale) }}"
     data-text-hint="{{ __('farmer.harvest_timing.reason_hint', [], $tLocale) }}"
     @if (! $tErrors->has('harvest_timing_reason') && ! $tErrors->has('harvest_timing_reason_other') && ! $tReason) hidden @endif>
    <p class="harvest-timing-hint" data-harvest-timing-hint role="status" aria-live="polite"></p>
    <label class="{{ $timingLabel ?? '' }}" for="harvest_timing_reason">{{ __('farmer.harvest_timing.reason', [], $tLocale) }}</label>
    <select class="{{ $timingInput ?? '' }}" id="harvest_timing_reason" name="harvest_timing_reason" data-harvest-timing-reason>
        <option value="">{{ __('farmer.harvest_timing.reason_select', [], $tLocale) }}</option>
        @foreach ($tReasons as $key => $label)
            <option value="{{ $key }}" @selected($tReason === $key)>{{ $label }}</option>
        @endforeach
    </select>
    @if ($tErrors->has('harvest_timing_reason'))<div class="{{ $timingError ?? '' }}" role="alert">{{ $tErrors->first('harvest_timing_reason') }}</div>@endif
    <div class="harvest-timing-other" data-harvest-timing-other @if ($tReason !== \App\Support\HarvestTiming::OTHER) hidden @endif>
        <label class="{{ $timingLabel ?? '' }}" for="harvest_timing_reason_other">{{ __('farmer.harvest_timing.other', [], $tLocale) }}</label>
        <input class="{{ $timingInput ?? '' }}" id="harvest_timing_reason_other" name="harvest_timing_reason_other" maxlength="255" value="{{ $tOther }}">
        @if ($tErrors->has('harvest_timing_reason_other'))<div class="{{ $timingError ?? '' }}" role="alert">{{ $tErrors->first('harvest_timing_reason_other') }}</div>@endif
    </div>
</div>
{{-- The behaviour is in resources/js/harvest-timing.js (loaded by app.js, so it also works in pop-ups). --}}
