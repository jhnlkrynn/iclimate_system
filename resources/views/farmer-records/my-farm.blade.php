<x-app-layout>
    {{-- "Aking Bukid" (FarmerMyFarmController): the farmer's own farms, the Activity Calendar (planted / harvested), the
         actual-harvest question, the planting schedule, harvest records (printable) and upcoming dates.
         Same look as the other farmer pages (.fad / .frec). --}}
    @php
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('M j, Y') : '—';
        $farmErrors = $errors->getBag('farm');
        $activityErrors = $errors->getBag('activity');
        $surveyErrors = $errors->getBag('yield_survey');
        $farmDialog = session('fmf_farm_dialog');          // "new" or a farm id: the pop-up to open again after a failed save
    @endphp

    <div class="fad frec fmf" data-my-farm>
        {{-- Printed on paper only (Print button): the office name and the print date. --}}
        <div class="fmf-print-head">
            <strong>{{ __('farmer.my_farm_page.print_header') }}</strong>
            <span>{{ __('farmer.my_farm_page.title') }} · {{ auth()->user()->name }}</span>
            <span>{{ __('farmer.my_farm_page.printed', ['date' => $printedAt->format('M j, Y g:i A')]) }}</span>
        </div>

        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farmer.nav.my_farm') }}</span>
                <h1>{{ __('farmer.my_farm_page.title') }}</h1>
                <p>{{ __('farmer.my_farm_page.intro') }}</p>
            </div>
            <div class="fad-hero-actions fmf-no-print">
                <button type="button" class="fad-btn is-light" onclick="window.print()">{{ __('farmer.my_farm_page.print') }}</button>
            </div>
        </header>

        {{-- My farms: add, edit, archive. The first farm is the one the profile and the MAO pages read. --}}
        <section class="frec-panel" id="farms" aria-labelledby="fmfFarmsTitle" data-fmf-farms>
            <div class="frec-panel-head is-row">
                <h2 id="fmfFarmsTitle">{{ __('farmer.farms.title') }}</h2>
                @if ($farmsReady)
                    <button type="button" class="fad-btn is-primary is-compact fmf-no-print" data-fmf-farm-open="new">{{ __('farmer.farms.add') }}</button>
                @endif
            </div>
            @if ($farms)
                <ul class="fmf-farms">
                    @foreach ($farms as $farm)
                        <li class="fmf-farm" data-fmf-farm="{{ $farm['id'] }}">
                            <div class="fmf-farm-main">
                                <strong class="fmf-farm-name">{{ $farm['name'] }}</strong>
                                @if ($farm['primary'] && count($farms) > 1)<span class="fad-badge is-good">{{ __('farmer.farms.main') }}</span>@endif
                                <span class="fmf-farm-facts">
                                    {{ $farm['barangay'] ?: '—' }} ·
                                    {{ $farm['area'] ? __('farmer.my_farm_page.hectares', ['value' => number_format($farm['area'], 2)]) : __('farmer.farms.no_area') }} ·
                                    {{ $farm['type'] ?: '—' }}
                                </span>
                            </div>
                            <div class="fmf-farm-actions fmf-no-print">
                                <button type="button" class="fad-btn is-quiet is-compact" data-fmf-farm-open="{{ $farm['id'] }}"
                                    data-name="{{ $farm['name'] }}" data-barangay="{{ $farm['barangay'] }}" data-area="{{ $farm['area'] !== null ? number_format($farm['area'], 2, '.', '') : '' }}" data-type="{{ $farm['farm_type'] }}"
                                    data-action="{{ route('farmer.farms.update', $farm['id']) }}">{{ __('farmer.farms.edit') }}</button>
                                <form method="POST" action="{{ route('farmer.farms.archive', $farm['id']) }}" data-fmf-confirm="{{ __('farmer.farms.archive_confirm') }}">
                                    @csrf
                                    <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farmer.farms.archive') }}</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="fad-support">{{ __('farmer.farms.none') }}</p>
            @endif
        </section>

        {{-- Activity Calendar: tap a date, choose Planted or Harvested and the farm. A grid on wide screens, a list on the phone. --}}
        <section class="frec-panel fmf-no-print" id="activity" aria-labelledby="fmfActivityTitle" data-fmf-activity>
            <div class="frec-panel-head is-row">
                <h2 id="fmfActivityTitle">{{ __('farmer.activity.title') }}</h2>
                @if ($farms)
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
                            <button type="submit" class="fad-btn is-primary">{{ __('farmer.farms.save') }}</button>
                            <button type="button" class="fad-btn is-quiet" data-fmf-survey-skip>{{ __('farmer.yield_survey.skip') }}</button>
                        </div>
                    </div>
                    <p class="fad-support">{{ __('farmer.yield_survey.hint') }}</p>
                    @if ($surveyErrors->any())<p class="fmf-error" role="alert">{{ $surveyErrors->first() }}</p>@endif
                </form>
            @endif

            @if (! $farms)
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
            @endif
        </section>
        @if (session('success'))
            <div class="frec-note fmf-no-print" role="status">{{ session('success') }}</div>
        @endif

        @if ($available)
            {{-- My Farms: every farm of this farmer, each with its own location, size and map. --}}
            <section class="frec-panel fmf-no-print" aria-labelledby="ffmListTitle" data-my-farms>
                <div class="frec-panel-head is-row">
                    <h2 id="ffmListTitle">{{ __('farms.title') }}</h2>
                    <a class="fad-btn is-compact" href="{{ route('farmer.farms.create') }}">+ {{ $farms ? __('farms.add_another') : __('farms.add') }}</a>
                </div>
                @if ($farms)
                    <p class="fad-m-small mb-0">{{ __('farms.intro') }}</p>
                    <ul class="ffm-cards">
                        @foreach ($farms as $f)
                            <li class="ffm-card @if ($f['current']) is-current @endif" data-farm-card="{{ $f['id'] }}">
                                <div class="ffm-card-head">
                                    <h3>{{ $f['name'] }}</h3>
                                    <div class="ffm-badges">
                                        @if ($f['primary'])<span class="fad-badge is-good">{{ __('farms.primary') }}</span>@endif
                                        @if ($f['current'])<span class="fad-badge is-info">{{ __('farms.current') }}</span>@endif
                                    </div>
                                </div>
                                <p class="ffm-card-place">{{ $f['barangay'] ? __('farms.location_line', ['barangay' => $f['barangay']]) : __('farms.no_barangay') }}@if ($f['location']) · {{ $f['location'] }}@endif</p>
                                <p class="ffm-card-size">{{ $f['size']['text'] }}@if ($f['size']['note'])<small>{{ $f['size']['note'] }}</small>@endif</p>
                                <p class="ffm-card-map"><span class="fad-badge {{ $f['mapped'] ? 'is-good' : 'is-muted' }}">{{ $f['mapped'] ? __('farms.mapped') : __('farms.not_mapped') }}</span></p>
                                <div class="ffm-card-actions">
                                    @unless ($f['current'])
                                        <form method="POST" action="{{ route('farmer.farms.select') }}">
                                            @csrf
                                            <input type="hidden" name="farm_id" value="{{ $f['id'] }}">
                                            <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farms.view') }}</button>
                                        </form>
                                    @endunless
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('farmer.farms.edit', $f['id']) }}">{{ __('farms.edit') }}</a>
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('farmer.farms.map', $f['id']) }}">{{ __('farms.map_action') }}</a>
                                    @unless ($f['primary'])
                                        <form method="POST" action="{{ route('farmer.farms.primary', $f['id']) }}">
                                            @csrf
                                            <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farms.set_primary') }}</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('farmer.farms.destroy', $f['id']) }}"
                                        data-ic-confirm="{{ count($farms) === 1 ? __('farms.remove_only_confirm', ['name' => $f['name']]) : __('farms.remove_confirm', ['name' => $f['name']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="fad-btn is-quiet is-compact ffm-remove">{{ __('farms.remove') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="ffm-empty">
                        <strong>{{ __('farms.add_first') }}</strong>
                        <p class="fad-m-small mb-0">{{ __('farms.add_first_note') }}</p>
                        <a class="fad-btn" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add') }}</a>
                    </div>
                @endif
            </section>
        @endif

        @if (! $available || $current)
            <section class="frec-panel" aria-labelledby="fmfInfoTitle">
                <div class="frec-panel-head is-row">
                    <h2 id="fmfInfoTitle">{{ $current ? $farm['name'] : __('farmer.my_farm_page.info') }}</h2>
                    @if ($current)
                        <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('farmer.farms.edit', $current) }}">{{ __('farms.edit') }}</a>
                    @else
                        <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('profile.edit') }}">{{ __('farmer.my_farm_page.edit_profile') }}</a>
                    @endif
                </div>
                <dl class="fmf-facts">
                    <div><dt>{{ __('farmer.my_farm_page.barangay') }}</dt><dd>{{ $farm['barangay'] ?: __('farmer.home.no_data') }}</dd></div>
                    @if ($farm['size'])
                        <div><dt>{{ __('farmer.my_farm_page.size') }}</dt><dd>{{ $farm['size']['text'] }}@if ($farm['size']['note'])<small class="d-block fad-m-small">{{ $farm['size']['note'] }}</small>@endif</dd></div>
                    @else
                        <div><dt>{{ __('farmer.my_farm_page.size') }}</dt><dd>{{ $farm['area'] ? __('farmer.my_farm_page.hectares', ['value' => number_format($farm['area'], 2)]) : __('farmer.home.no_data') }}</dd></div>
                    @endif
                    <div><dt>{{ __('farmer.my_farm_page.type') }}</dt><dd>{{ $farm['type'] ?: __('farmer.home.no_data') }}</dd></div>
                </dl>
            </section>
        @endif

        <section class="frec-panel" aria-labelledby="fmfScheduleTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfScheduleTitle">{{ __('farmer.my_farm_page.schedule') }}</h2>
                <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('farmer.planting-schedules.create', $current ? ['farm' => $current->getKey()] : []) }}">{{ __('farmer.my_farm_page.add_schedule') }}</a>
            </div>
            @if ($schedules)
                <div class="frec-table-wrap">
                    <table class="frec-table">
                        <thead><tr>
                            <th scope="col">{{ __('farmer.my_farm_page.planted') }}</th>
                            @if ($showFarmColumn)<th scope="col">{{ __('farmer.farms.farm') }}</th>@endif
                            <th scope="col">{{ __('farmer.my_farm_page.harvest_due') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.status') }}</th>
                            <th scope="col" class="is-end fmf-no-print"><span class="visually-hidden">{{ __('farmer.my_farm_page.edit') }}</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($schedules as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['planting_date']) }}</th>
                                    @if ($showFarmColumn)<td>{{ $row['farm'] ?? '—' }}</td>@endif
                                    <td>{{ $date($row['harvest_date']) }}</td>
                                    <td>{{ $row['season'] ?? '—' }}</td>
                                    <td>{{ $row['status'] }}</td>
                                    <td class="is-end fmf-no-print"><a class="fad-btn is-quiet is-compact" href="{{ route('farmer.planting-schedules.edit', $row['id']) }}">{{ __('farmer.my_farm_page.edit') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_schedule')" />
            @endif
        </section>

        <section class="frec-panel fmf-harvests" id="harvests" aria-labelledby="fmfHarvestTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfHarvestTitle">{{ __('farmer.my_farm_page.harvests') }}</h2>
            </div>
            @if ($harvests)
                <div class="frec-table-wrap">
                    <table class="frec-table">
                        <thead><tr>
                            <th scope="col">{{ __('farmer.my_farm_page.harvested_on') }}</th>
                            @if ($showFarmColumn)<th scope="col">{{ __('farmer.farms.farm') }}</th>@endif
                            <th scope="col">{{ __('farmer.my_farm_page.planted') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col" class="is-num">{{ __('farmer.my_farm_page.yield') }} (t/ha)</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($harvests as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['harvested_on']) }}</th>
                                    @if ($showFarmColumn)<td>{{ $row['farm'] ?? '—' }}</td>@endif
                                    <td>{{ $date($row['planting_date']) }}</td>
                                    <td>{{ $row['season'] ?? '—' }}</td>
                                    <td class="is-num">{{ number_format($row['yield'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_harvests')" />
            @endif
        </section>

        <section class="frec-panel fmf-no-print" aria-labelledby="fmfEventsTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfEventsTitle">{{ __('farmer.my_farm_page.events') }}</h2>
                <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index') }}">{{ __('farmer.my_farm_page.open_calendar') }}</a>
            </div>
            @if ($events)
                <ul class="fmf-events">
                    @foreach ($events as $event)
                        <li><strong>{{ $date($event['date']) }}</strong><span>{{ $event['label'] }}</span></li>
                    @endforeach
                </ul>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_events')" />
            @endif
        </section>

        {{-- Add / edit a farm. Full width on the phone. Without scripts the form is simply shown on the page. --}}
        @if ($farmsReady)
            <dialog class="fmf-dialog" data-fmf-farm-dialog aria-labelledby="fmfFarmDialogTitle" @if ($farmDialog) data-fmf-open="{{ $farmDialog }}" @endif
                data-title-new="{{ __('farmer.farms.add_title') }}" data-title-edit="{{ __('farmer.farms.edit_title') }}" data-action-new="{{ route('farmer.farms.store') }}">
                <form method="POST" action="{{ route('farmer.farms.store') }}">
                    @csrf
                    <input type="hidden" name="_method" value="POST" data-fmf-method>
                    <h2 id="fmfFarmDialogTitle" data-fmf-farm-title>{{ __('farmer.farms.add_title') }}</h2>
                    @if ($farmErrors->any())
                        <ul class="fmf-error" role="alert">@foreach ($farmErrors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
                    @endif
                    <label class="fmf-field">
                        <span>{{ __('farmer.farms.name') }}</span>
                        <input class="fad-input" type="text" name="name" maxlength="100" required value="{{ old('name') }}" placeholder="{{ __('farmer.farms.name_hint') }}">
                    </label>
                    <label class="fmf-field">
                        <span>{{ __('farmer.farms.barangay') }}</span>
                        <select class="fad-input" name="barangay" required>
                            <option value="">{{ __('farmer.farms.choose_barangay') }}</option>
                            @foreach ($barangays as $name)<option value="{{ $name }}" @selected(old('barangay') === $name)>{{ $name }}</option>@endforeach
                        </select>
                    </label>
                    <label class="fmf-field">
                        <span>{{ __('farmer.farms.area') }}</span>
                        <input class="fad-input" type="number" name="area_hectares" inputmode="decimal" min="0.01" max="10000" step="0.01" required value="{{ old('area_hectares') }}">
                    </label>
                    <fieldset class="fmf-field fmf-choices">
                        <legend>{{ __('farmer.farms.type') }}</legend>
                        @foreach ($farmTypes as $value => $label)
                            <label class="fmf-choice"><input type="radio" name="farm_type" value="{{ $value }}" required @checked(old('farm_type', 'Irrigated') === $value)><span>{{ $label }}</span></label>
                        @endforeach
                    </fieldset>
                    <div class="fmf-dialog-actions">
                        <button type="submit" class="fad-btn is-primary">{{ __('farmer.farms.save') }}</button>
                        <button type="button" class="fad-btn is-quiet" data-fmf-close>{{ __('farmer.farms.cancel') }}</button>
                    </div>
                </form>
            </dialog>
        @endif

        {{-- Record an activity: the date, Planted or Harvested, which farm. --}}
        @if ($farms)
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
                            @foreach ($farms as $farm)<option value="{{ $farm['id'] }}" @selected((string) old('farm_id') === (string) $farm['id'])>{{ $farm['name'] }}</option>@endforeach
                        </select>
                    </label>
                    <div class="fmf-dialog-actions">
                        <button type="submit" class="fad-btn is-primary">{{ __('farmer.farms.save') }}</button>
                        <button type="button" class="fad-btn is-quiet" data-fmf-close>{{ __('farmer.farms.cancel') }}</button>
                    </div>
                </form>
            </dialog>
        @endif
    </div>
</x-app-layout>
