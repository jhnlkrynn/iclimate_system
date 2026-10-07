{{-- Farmer – Naicel (2026-10): the Activity Calendar and the actual-harvest question on My Farm. Tap a date, choose
     Planted or Harvested and the farm (John Luke's farms). A grid on wide screens, a list by month on the phone.
     Saved in the existing planting records (FarmerActivityController). --}}
@php
    $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('M j, Y') : '—';
    $activityErrors = $errors->getBag('activity');
    $surveyErrors = $errors->getBag('yield_survey');
@endphp
<section class="frec-panel fmf-no-print" id="activity" aria-labelledby="fmfActivityTitle" data-fmf-activity>
    <div class="frec-panel-head is-row">
        <h2 id="fmfActivityTitle">{{ __('farmer.activity.title') }}</h2>
        @if ($activityFarms)
            <button type="button" class="fad-btn is-primary is-compact" data-fmf-activity-open="{{ $calendar['default_date'] }}">{{ __('farmer.activity.add') }}</button>
        @endif
    </div>

    @if ($yieldSurvey)
        {{-- Asked after a harvest is recorded. Stored only: it never retrains the model. --}}
        <form class="fmf-survey" id="yield-survey" method="POST" action="{{ route('farmer.yield-survey.store', $yieldSurvey['id']) }}" data-fmf-yield-survey="{{ $yieldSurvey['id'] }}">
            @csrf
            <div class="fmf-survey-text">
                <strong>{{ __('farmer.yield_survey.title') }}</strong>
                <span class="fad-support">{{ collect([$yieldSurvey['farm'], $yieldSurvey['season'], __('farmer.my_farm_page.harvested_on').' '.$date($yieldSurvey['harvested_on'])])->filter()->implode(' · ') }}</span>
            </div>
            <div class="fmf-survey-row">
                <label class="fmf-field">
                    <span>{{ __('farmer.yield_survey.actual') }}</span>
                    <input class="fad-input" type="number" name="actual_yield" inputmode="decimal" min="0.01" max="15" step="0.01" required value="{{ old('actual_yield') }}">
                </label>
                <p class="fmf-survey-predicted" data-fmf-predicted>
                    <span>{{ __('farmer.yield_survey.predicted') }}</span>
                    @if ($yieldSurvey['predicted'] !== null)
                        <strong>{{ number_format($yieldSurvey['predicted'], 2) }} <small>t/ha</small></strong>
                    @else
                        <em>{{ __('farmer.yield_survey.no_prediction') }}</em>
                    @endif
                </p>
                <div class="fmf-survey-actions">
                    <button type="submit" class="fad-btn is-primary">{{ __('farmer.activity.save') }}</button>
                    <button type="button" class="fad-btn is-quiet" data-fmf-survey-skip>{{ __('farmer.yield_survey.skip') }}</button>
                </div>
            </div>
            <p class="fad-support">{{ __('farmer.yield_survey.hint') }}</p>
            @if ($surveyErrors->any())<p class="fmf-error" role="alert">{{ $surveyErrors->first() }}</p>@endif
        </form>
    @endif

    @if (! $activityFarms)
        <p class="fad-support">{{ __('farmer.activity.need_farm') }}</p>
    @else
        <p class="fad-support fmf-cal-intro">{{ __('farmer.activity.intro') }}</p>
        <div class="fmf-cal-nav">
            <a class="fad-btn is-quiet is-compact" href="{{ $calendar['prev'] }}" aria-label="{{ __('farmer.activity.prev') }}">‹</a>
            <strong data-fmf-month="{{ $calendar['month'] }}">{{ $calendar['title'] }}</strong>
            <a class="fad-btn is-quiet is-compact" href="{{ $calendar['next'] }}" aria-label="{{ __('farmer.activity.next') }}">›</a>
        </div>

        <table class="fmf-cal" data-fmf-grid>
            <thead><tr>
                @foreach (__('farmer.activity.weekdays') as $weekday)<th scope="col">{{ $weekday }}</th>@endforeach
            </tr></thead>
            <tbody>
                @foreach ($calendar['weeks'] as $week)
                    <tr>
                        @foreach ($week as $day)
                            <td @class(['is-out' => ! $day['in_month'], 'is-today' => $day['is_today']])>
                                @if ($day['in_month'])
                                    <button type="button" class="fmf-cal-day" data-fmf-activity-open="{{ $day['date'] }}" aria-label="{{ $day['label'] }}">
                                        <span class="fmf-cal-num">{{ $day['day'] }}</span>
                                        @foreach ($day['events'] as $event)
                                            <span class="fmf-cal-event is-{{ $event['kind'] }}">{{ $event['label'] }}@if ($event['farm'])<small>{{ $event['farm'] }}</small>@endif</span>
                                        @endforeach
                                    </button>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- The same month as a list (the phone shows only this). --}}
        <div class="fmf-cal-list" data-fmf-list>
            @forelse ($calendar['list'] as $row)
                @foreach ($row['events'] as $event)
                    <a class="fmf-cal-item is-{{ $event['kind'] }}" href="{{ $event['edit'] }}">
                        <strong>{{ $row['date']->locale(app()->getLocale())->translatedFormat('M j') }}</strong>
                        <span>{{ $event['label'] }}@if ($event['farm']) · {{ $event['farm'] }}@endif</span>
                    </a>
                @endforeach
            @empty
                <p class="fad-support">{{ __('farmer.activity.none_month') }}</p>
            @endforelse
        </div>

        {{-- Record an activity: the date, Planted or Harvested, which farm. Full width on the phone. --}}
        <dialog class="fmf-dialog" data-fmf-activity-dialog aria-labelledby="fmfActivityDialogTitle" @if (session('fmf_activity_dialog')) data-fmf-open="1" @endif>
            <form method="POST" action="{{ route('farmer.activities.store') }}">
                @csrf
                <h2 id="fmfActivityDialogTitle">{{ __('farmer.activity.dialog_title') }}</h2>
                @if ($activityErrors->any())
                    <ul class="fmf-error" role="alert">@foreach ($activityErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                @endif
                <label class="fmf-field">
                    <span>{{ __('farmer.activity.date') }}</span>
                    <input class="fad-input" type="date" name="date" required min="{{ $calendar['min'] }}" max="{{ $calendar['max'] }}" value="{{ old('date', $calendar['default_date']) }}">
                </label>
                <fieldset class="fmf-field fmf-choices">
                    <legend>{{ __('farmer.activity.what') }}</legend>
                    @foreach (['planted', 'harvested'] as $kind)
                        <label class="fmf-choice"><input type="radio" name="kind" value="{{ $kind }}" required @checked(old('kind', 'planted') === $kind)><span>{{ __('farmer.activity.'.$kind) }}</span></label>
                    @endforeach
                </fieldset>
                <label class="fmf-field">
                    <span>{{ __('farmer.activity.farm') }}</span>
                    <select class="fad-input" name="farm_id" required>
                        @foreach ($activityFarms as $farmOption)
                            <option value="{{ $farmOption['id'] }}" @selected((string) old('farm_id', $activityFarmId) === (string) $farmOption['id'])>{{ $farmOption['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="fmf-dialog-actions">
                    <button type="submit" class="fad-btn is-primary">{{ __('farmer.activity.save') }}</button>
                    <button type="button" class="fad-btn is-quiet" data-fmf-close>{{ __('farmer.activity.cancel') }}</button>
                </div>
            </form>
        </dialog>
    @endif
</section>
