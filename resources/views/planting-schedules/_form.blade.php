{{-- The planting plan form, shared by the standalone page (add/edit, Bootstrap fields) and the Calendar pop-up
     ($inModal: the same .fad-m-* parts and .fad-input fields as the Advisories pop-up). --}}
@php
    $inModal = $inModal ?? false;
    $c = $inModal
        ? ['grid' => 'fcal-plan-grid', 'field' => 'fad-field', 'full' => 'fad-field is-full', 'label' => 'fad-m-field-label', 'input' => 'fad-input', 'select' => 'fad-input', 'help' => 'fad-m-small', 'error' => 'fcal-plan-error']
        : ['grid' => 'schedule-form-grid', 'field' => 'schedule-form-field', 'full' => 'schedule-form-field is-full', 'label' => 'form-label', 'input' => 'form-control', 'select' => 'form-select', 'help' => 'schedule-help', 'error' => 'invalid-feedback d-block'];
    $invalid = fn (string $field) => $errors->has($field) ? ' is-invalid' : '';
    $farmField = $inModal ? $c['full'] : $c['field'];
    // Rice Varieties: the variety fields, once the database has them.
    $varietiesReady = \App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'rice_variety_id') && \App\Support\SchemaCache::hasTable('rice_varieties');
@endphp
@unless($inModal)
    <style>
        .schedule-form label { color: var(--ic-ink, #0d1f18); font-size: 1rem; font-weight: 750; }
        .schedule-form .form-control, .schedule-form .form-select { min-height: 48px; font-size: 1rem; }
        .schedule-help { color: var(--ic-ink-mid, #3d5a48); font-size: .92rem; line-height: 1.55; }
        .season-guide { padding: .9rem 1rem; border-radius: 10px; background: rgba(82,183,136,.1); color: var(--ic-ink, #0d1f18); font-size: .95rem; }
    </style>
@endunless

<form method="POST" action="{{ $action }}" class="schedule-form" @if($inModal) data-plan-form @endif>
    @csrf
    @if($method !== 'POST') @method($method) @endif

    @if($inModal)<div class="fad-m-scroll">@endif

    @if($errors->any())
        <div class="{{ $inModal ? 'fad-m-note is-danger' : 'alert alert-danger' }}" role="alert">
            <strong>Please check the highlighted information.</strong>
        </div>
    @endif

    <div class="{{ $inModal ? 'fad-m-note is-good' : 'season-guide mb-4' }}">
        <strong>Season guide:</strong> <span>Wet season planting is normally May–July. Dry season planting is normally October–December. Common harvest months are March, April, October, and November.</span>
    </div>
    <div id="scheduleDateWarning" class="{{ $inModal ? 'fad-m-note is-watch' : 'alert alert-warning schedule-date-warning' }} d-none" role="status" aria-live="polite"></div>

    <div class="{{ $c['grid'] }}">
        @php $planFarms = $farms ?? collect(); @endphp
        @if ($planFarms->isNotEmpty())
            {{-- Which of the farmer's farms the plan is for (their own farms only; defaults to the farm they are viewing). --}}
            <div class="{{ $farmField }}">
                <label class="{{ $c['label'] }}" for="plan_farm_id">{{ __('farms.plan_farm') }}</label>
                <select class="{{ $c['select'] }}{{ $invalid('farm_id') }}" id="plan_farm_id" name="farm_id">
                    @foreach ($planFarms as $planFarm)
                        <option value="{{ $planFarm->id }}" @selected((int) old('farm_id', $schedule->farm_id ?? \App\Support\ActiveFarm::current(auth()->user())?->id) === (int) $planFarm->id)>{{ $planFarm->farm_name }}{{ $planFarm->barangay ? ' · '.$planFarm->barangay : '' }}</option>
                    @endforeach
                </select>
                @error('farm_id')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
                <div class="{{ $c['help'] }}">{{ __('farms.plan_farm_hint') }}</div>
            </div>
        @endif
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="planting_date">Planting date</label>
            <input class="{{ $c['input'] }}{{ $invalid('planting_date') }}" id="planting_date" name="planting_date" type="date" required value="{{ old('planting_date', $schedule->planting_date?->format('Y-m-d')) }}">
            @error('planting_date')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
            @if ($varietiesReady)
                {{-- Rice Varieties: "Expected harvest: Feb 16, 2027 (± 4 days)" for the chosen variety. --}}
                <div class="{{ $c['help'] }} rv-preview" data-rv-preview aria-live="polite" hidden>
                    <strong data-rv-preview-label></strong>
                    <span class="rv-preview-basis" data-rv-preview-basis></span>
                    <button type="button" class="rv-preview-use" data-rv-use hidden>{{ __('rice_varieties.estimate.use') }}</button>
                </div>
            @endif
        </div>
        @if ($varietiesReady)
            @include('rice-varieties._planting-fields')
        @endif
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="scheduled_harvest_date">Expected harvest date</label>
            <input class="{{ $c['input'] }}{{ $invalid('scheduled_harvest_date') }}" id="scheduled_harvest_date" name="scheduled_harvest_date" type="date" value="{{ old('scheduled_harvest_date', $schedule->scheduled_harvest_date?->format('Y-m-d')) }}">
            @error('scheduled_harvest_date')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
            <div class="{{ $c['help'] }}">{{ $varietiesReady ? 'Filled in from your rice variety. Change it only if you know a different date.' : 'Use an exact date, or choose only a harvest month below.' }}</div>
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="harvest_month">Harvest month</label>
            <input class="{{ $c['input'] }}{{ $invalid('harvest_month') }}" id="harvest_month" name="harvest_month" type="month" value="{{ old('harvest_month', $schedule->harvest_month?->format('Y-m')) }}">
            @error('harvest_month')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="season">Season</label>
            <select class="{{ $c['select'] }}{{ $invalid('season') }}" id="season" name="season" required>
                <option value="">Choose season</option>
                @foreach(\App\Models\FarmerPlantingSchedule::SEASONS as $season)
                    <option value="{{ $season }}" @selected(old('season', $schedule->season) === $season)>{{ $season }}</option>
                @endforeach
            </select>
            @error('season')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="farm_type">Type of farm</label>
            <select class="{{ $c['select'] }}{{ $invalid('farm_type') }}" id="farm_type" name="farm_type" required>
                <option value="">Choose type of farm</option>
                @foreach(\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)
                    <option value="{{ $farmType }}" @selected(old('farm_type', $schedule->farm_type) === $farmType)>{{ $farmType }}</option>
                @endforeach
            </select>
            @error('farm_type')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="barangay">Barangay / farm location</label>
            <select class="{{ $c['select'] }}{{ $invalid('barangay') }}" id="barangay" name="barangay">
                <option value="">Not available</option>
                @foreach($barangays as $barangay)<option value="{{ $barangay }}" @selected(old('barangay', $schedule->barangay) === $barangay)>{{ $barangay }}</option>@endforeach
            </select>
            @error('barangay')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="farm_area_hectares">Farm size (hectares)</label>
            <input class="{{ $c['input'] }}{{ $invalid('farm_area_hectares') }}" id="farm_area_hectares" name="farm_area_hectares" type="number" min="0.01" max="{{ config('farms.max_hectares', 10000) }}" step="0.01" value="{{ old('farm_area_hectares', $schedule->farm_area_hectares) }}">
            @error('farm_area_hectares')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
            <div class="{{ $c['help'] }}">Needed to estimate your harvest. Leave blank if unknown.</div>
        </div>
        <div class="{{ $c['field'] }}">
            <label class="{{ $c['label'] }}" for="progress_status">Planting stage</label>
            <select class="{{ $c['select'] }}{{ $invalid('progress_status') }}" id="progress_status" name="progress_status">
                <option value="">Automatic from dates</option>
                @foreach(\App\Models\FarmerPlantingSchedule::MANUAL_STATUSES as $status)<option value="{{ $status }}" @selected(old('progress_status', $schedule->progress_status) === $status)>{{ $status }}</option>@endforeach
            </select>
            @error('progress_status')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
        @if (\App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield'))
            <div class="{{ $c['field'] }}">
                <label class="{{ $c['label'] }}" for="actual_yield">My harvest (tons per hectare)</label>
                <input class="{{ $c['input'] }}{{ $invalid('actual_yield') }}" id="actual_yield" name="actual_yield" type="number" min="0.01" max="15" step="0.01" value="{{ old('actual_yield', $schedule->actual_yield) }}">
                @error('actual_yield')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
                <div class="{{ $c['help'] }}">After harvest only. Your total harvest in tons divided by your farm size in hectares. Your own records make your yield comparison more accurate.</div>
                {{-- Panel revision 1.2: or type the harvest in sacks, kilograms or total tons. --}}
                @include('partials.yield-converter', ['target' => '#actual_yield', 'area' => '#farm_area_hectares', 'converterInput' => $c['input'], 'converterHelp' => $c['help']])
            </div>
            <div class="{{ $c['field'] }}">
                <label class="{{ $c['label'] }}" for="harvested_on">Date harvested</label>
                <input class="{{ $c['input'] }}{{ $invalid('harvested_on') }}" id="harvested_on" name="harvested_on" type="date" max="{{ \App\Support\BusinessClock::today()->toDateString() }}" value="{{ old('harvested_on', $schedule->harvested_on?->format('Y-m-d')) }}">
                @error('harvested_on')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
                @if ($schedule->harvest_timing ?? null)
                    <div class="{{ $c['help'] }}" data-harvest-timing-saved>{{ __('farmer.harvest_timing.label') }}: <strong>{{ $schedule->harvestTimingSummary() }}</strong></div>
                @endif
            </div>
            {{-- Panel revision 1.3: the reason for an early or late harvest. --}}
            @if (\App\Support\SchemaCache::hasColumn('farmer_planting_schedules', 'harvest_timing'))
                @include('partials.harvest-timing-fields', [
                    'timingWrap' => $c['full'], 'timingLabel' => $c['label'], 'timingInput' => $c['select'], 'timingError' => $c['error'],
                    'timingReason' => $schedule->harvest_timing_reason ?? null, 'timingOther' => $schedule->harvest_timing_reason_other ?? null,
                ])
            @endif
        @endif
        <div class="{{ $c['full'] }}">
            <label class="{{ $c['label'] }}" for="notes">Notes (optional)</label>
            <textarea class="{{ $c['input'] }}{{ $invalid('notes') }}" id="notes" name="notes" rows="{{ $inModal ? 3 : 4 }}" maxlength="2000">{{ old('notes', $schedule->notes) }}</textarea>
            @error('notes')<div class="{{ $c['error'] }}">{{ $message }}</div>@enderror
        </div>
    </div>

    @if($inModal)
        </div>
        <footer class="fad-m-foot">
            <button class="fad-btn is-primary" type="submit">Save Schedule</button>
            <button class="fad-btn is-quiet" type="button" data-plan-close>Cancel</button>
        </footer>
    @else
        <div class="schedule-form-actions d-flex flex-column flex-sm-row gap-2 mt-4">
            <button class="btn btn-primary btn-lg" type="submit">Save Schedule</button>
            <a class="btn btn-outline-secondary btn-lg" href="{{ route('farmer.my-farm') }}">Cancel</a>
        </div>
    @endif
</form>
<script>
    (() => {
        const planting = document.getElementById('planting_date');
        const harvestDate = document.getElementById('scheduled_harvest_date');
        const harvestMonth = document.getElementById('harvest_month');
        const season = document.getElementById('season');
        const warning = document.getElementById('scheduleDateWarning');
        const monthOf = (value) => value ? Number(value.split('-')[1]) : null;
        const review = () => {
            const messages = [];
            const plantingMonth = monthOf(planting.value);
            const expected = season.value === 'Wet' ? [5,6,7] : (season.value === 'Dry' ? [11,12,1] : []);
            if (plantingMonth && expected.length && !expected.includes(plantingMonth)) messages.push(`${season.value} season planting is normally ${season.value === 'Wet' ? 'May to July' : 'November to January'}.`);
            const selectedHarvestMonth = monthOf(harvestDate.value || harvestMonth.value);
            if (selectedHarvestMonth && ![3,4,10,11].includes(selectedHarvestMonth)) messages.push('Common harvest months are March, April, October, and November.');
            warning.innerHTML = messages.length ? `<strong>Date warning:</strong> <span>${messages.join(' ')} You may still save this schedule.</span>` : '';
            warning.classList.toggle('d-none', messages.length === 0);
        };
        [planting, harvestDate, harvestMonth, season].forEach((field) => field?.addEventListener('change', review));
        review();
    })();
</script>
