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
            {{-- Panel revision 1.2: or type the harvest in sacks, kilograms or tons; it is turned into t/ha. --}}
            <label class="fmf-field">
                <span>{{ app()->getLocale() === 'fil' ? 'Laki ng inani (ektarya)' : 'Area harvested (hectares)' }}</span>
                <input class="fad-input" type="number" id="ysArea" min="0.01" step="0.01" inputmode="decimal" value="{{ $yieldSurvey['area'] ?? '' }}">
            </label>
            @include('partials.yield-converter', ['target' => '[name=actual_yield]', 'area' => '#ysArea', 'converterInput' => 'fad-input', 'converterHelp' => 'fad-support'])
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
                                            <span class="fmf-cal-event is-{{ $event['kind'] }}"@if (! empty($event['timing_text'])) title="{{ $event['timing_text'] }}"@endif>{{ $event['label'] }}@if ($event['farm'])<small>{{ $event['farm'] }}</small>@endif
                                                @if (! empty($event['variety']))<small data-harvest-variety>{{ $event['variety'] }}</small>@endif
                                                @if (! empty($event['timing']))<small class="timing-badge is-{{ \Illuminate\Support\Str::slug($event['timing']) }}">{{ \App\Support\HarvestTiming::timingLabel($event['timing']) }}</small>@endif
                                            </span>
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
                        <span>{{ $event['label'] }}@if ($event['farm']) · {{ $event['farm'] }}@endif @if (! empty($event['variety'])) · <span data-harvest-variety>{{ $event['variety'] }}</span>@endif
                            @if (! empty($event['timing_text']))<small class="timing-line is-{{ \Illuminate\Support\Str::slug($event['timing']) }}">{{ $event['timing_text'] }}</small>@endif
                        </span>
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
                            <option value="{{ $farmOption['id'] }}"
                                data-expected-harvest="{{ $farmOption['expected_harvest'] ?? '' }}"
                                data-open-planting="{{ json_encode($farmOption['open_planting'] ?? null) }}"
                                @selected((string) old('farm_id', $activityFarmId) === (string) $farmOption['id'])>{{ $farmOption['name'] }}</option>
                        @endforeach
                    </select>
                </label>
                @php
                    $actFil = app()->getLocale() === 'fil';
                    $actVarietyOld = (string) old('rice_variety_id', '');
                    $actVarietyLabel = '';
                    if ($actVarietyOld === 'unknown') {
                        $actVarietyLabel = $actFil ? 'Hindi ko alam' : "I don't know";
                    } elseif ($actVarietyOld !== '') {
                        $matchedVariety = collect($activityVarieties ?? [])->firstWhere('id', (int) $actVarietyOld);
                        $actVarietyLabel = $matchedVariety['label'] ?? '';
                    }
                    $actComboOptions = array_merge(
                        [['id' => 'unknown', 'label' => $actFil ? 'Hindi ko alam' : "I don't know", 'search' => 'unknown hindi ko alam i dont know']],
                        $activityVarieties ?? []
                    );
                @endphp
                <div class="fmf-field fmf-combo" data-fmf-variety-wrap @if (old('kind', 'planted') !== 'planted') hidden @endif>
                    <label for="fmfVarietySearch" class="d-block mb-1">{{ $actFil ? 'Rice variety' : 'Variety' }}</label>
                    <input type="hidden" name="rice_variety_id" value="{{ $actVarietyOld }}" data-fmf-variety-value>
                    <input id="fmfVarietySearch" type="search" class="fad-input" role="combobox" aria-autocomplete="list" aria-expanded="false"
                        placeholder="{{ $actFil ? 'Maghanap ng variety (hal. NSIC Rc 222)...' : 'Search variety (e.g. NSIC Rc 222)...' }}"
                        value="{{ $actVarietyLabel }}"
                        autocomplete="off"
                        data-fmf-variety-search
                        data-options="{{ json_encode($actComboOptions) }}"
                        data-empty-label="{{ $actFil ? 'Walang katugmang variety.' : 'No matching variety found.' }}"
                        @if (old('kind', 'planted') === 'planted') required @endif>
                    <ul class="fmf-combo-list" role="listbox" data-fmf-variety-list hidden></ul>
                </div>
                <div class="rv-preview" data-fmf-harvest-preview data-estimate-url="{{ route('rice-varieties.estimate') }}" hidden aria-live="polite">
                    <strong data-fmf-preview-summary></strong>
                    <span class="rv-preview-basis" data-fmf-preview-today></span>
                </div>
                <div class="fad-m-note is-good" data-fmf-closing-ref hidden aria-live="polite"
                    data-prefix="{{ $actFil ? 'Isinasarang tanim' : 'Closing' }}"
                    data-planted-word="{{ $actFil ? 'itinanim noong' : 'planted' }}"
                    data-expected-word="{{ $actFil ? 'Inaasahang ani' : 'Expected harvest' }}"
                    data-unknown-variety="{{ $actFil ? 'Hindi alam ang variety' : 'Unknown variety' }}">
                </div>
                {{-- Panel revision 1.3: asked only when the harvest date is early or late. --}}
                @include('partials.harvest-timing-fields', ['timingWrap' => 'fmf-field', 'timingInput' => 'fad-input', 'timingError' => 'fmf-error', 'timingBag' => 'activity'])
                <div class="fmf-dialog-actions">
                    <button type="submit" class="fad-btn is-primary">{{ __('farmer.activity.save') }}</button>
                    <button type="button" class="fad-btn is-quiet" data-fmf-close>{{ __('farmer.activity.cancel') }}</button>
                </div>
            </form>
        </dialog>
    @endif
</section>
