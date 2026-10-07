{{-- Farmer – Naicel (2026-10): the small forecast survey. Asked once for yesterday, can be skipped, never blocks the
     page. $survey = ['date' => 'Y-m-d', 'condition' => the forecast in the survey's words]. --}}
@php
    $day = \Illuminate\Support\Carbon::parse($survey['date'])->locale(app()->getLocale())->translatedFormat('M j');
    $word = fn (string $condition) => \Illuminate\Support\Facades\Lang::has('farmer.forecast_survey.conditions.'.$condition) ? __('farmer.forecast_survey.conditions.'.$condition) : $condition;
@endphp
<form class="fad-card wpx-survey" method="POST" action="{{ route('farmer.forecast-survey.store') }}" data-forecast-survey="{{ $survey['date'] }}"
    data-text-failed="{{ __('farmer.forecast_survey.failed') }}" aria-labelledby="wpxSurveyTitle">
    @csrf
    <input type="hidden" name="forecast_date" value="{{ $survey['date'] }}">
    <div class="wpx-survey-head">
        <div>
            <h2 id="wpxSurveyTitle">{{ __('farmer.forecast_survey.question') }}</h2>
            <p class="fad-support">{{ __('farmer.forecast_survey.forecast_was', ['date' => $day, 'condition' => $word($survey['condition'])]) }}</p>
        </div>
        <button type="button" class="fad-btn is-quiet is-compact" data-forecast-survey-skip>{{ __('farmer.forecast_survey.skip') }}</button>
    </div>
    <fieldset class="wpx-survey-choices">
        <legend class="visually-hidden">{{ __('farmer.forecast_survey.question') }}</legend>
        @foreach (\App\Models\WeatherForecastFeedback::ACCURACY_VALUES as $value)
            <label class="wpx-survey-choice"><input type="radio" name="accuracy" value="{{ $value }}" required><span>{{ __('farmer.forecast_survey.answers.'.$value) }}</span></label>
        @endforeach
    </fieldset>
    {{-- Asked when the forecast was not right (with scripts it appears after "Medyo" or "Mali"). --}}
    <fieldset class="wpx-survey-choices" data-forecast-survey-observed>
        <legend>{{ __('farmer.forecast_survey.observed') }}</legend>
        @foreach (\App\Http\Controllers\FarmerForecastSurveyController::OBSERVED as $value)
            <label class="wpx-survey-choice"><input type="radio" name="observed_condition" value="{{ $value }}"><span>{{ $word($value) }}</span></label>
        @endforeach
    </fieldset>
    <div class="wpx-survey-actions">
        <button type="submit" class="fad-btn is-primary">{{ __('farmer.forecast_survey.send') }}</button>
        <span class="fad-support" role="status" aria-live="polite" data-forecast-survey-status></span>
    </div>
</form>
