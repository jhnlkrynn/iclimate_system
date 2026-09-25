<x-app-layout>
    @include('layouts.partials.dark-workspace')
    <div class="dark-workspace">
        <style>
            .schedule-form-card { max-width: 920px; margin: 0 auto; border: 1px solid var(--ic-border); border-radius: 14px; }
            .schedule-form-card label { color: var(--ic-ink); font-size: 1rem; font-weight: 750; }
            .schedule-form-card .form-control, .schedule-form-card .form-select { min-height: 48px; font-size: 1rem; }
            .schedule-help { color: var(--ic-ink-mid); font-size: .92rem; line-height: 1.55; }
            .season-guide { padding: .9rem 1rem; border-radius: 10px; background: rgba(82,183,136,.1); color: var(--ic-ink); font-size: .95rem; }
            @media (max-width: 575.98px) { .schedule-form-card .card-body { padding: 1rem !important; } }
        </style>

        <section class="page-hero">
            <div class="eyebrow mb-2">Farmer Calendar</div>
            <h1 class="h2 fw-bold mb-2">{{ $schedule->exists ? 'Edit My Planting Plan' : 'Add My Planting Plan' }}</h1>
            <p class="mb-0" style="color:var(--ic-ink-mid)">Record your field plan. Unusual dates show a warning, but you can still save them.</p>
        </section>

        <div class="card no-lift schedule-form-card">
            <div class="card-body p-4">
                @if($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <strong>Please check the highlighted information.</strong>
                        <ul class="mb-0 mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    </div>
                @endif

                <div class="season-guide mb-4">
                    <strong>Season guide:</strong> Wet-season planting is normally May–July. Dry-season planting is normally October–December. Common harvest months are March, April, October, and November.
                </div>
                <div id="scheduleDateWarning" class="alert alert-warning d-none" role="status" aria-live="polite"></div>

                <form method="POST" action="{{ $action }}">
                    @csrf
                    @if($method !== 'POST') @method($method) @endif
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="planting_date">Planting date</label>
                            <input class="form-control @error('planting_date') is-invalid @enderror" id="planting_date" name="planting_date" type="date" required value="{{ old('planting_date', $schedule->planting_date?->format('Y-m-d')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="scheduled_harvest_date">Expected harvest date</label>
                            <input class="form-control @error('scheduled_harvest_date') is-invalid @enderror" id="scheduled_harvest_date" name="scheduled_harvest_date" type="date" value="{{ old('scheduled_harvest_date', $schedule->scheduled_harvest_date?->format('Y-m-d')) }}">
                            <div class="schedule-help mt-1">Use an exact date, or choose only a harvest month below.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="harvest_month">Harvest month</label>
                            <input class="form-control @error('harvest_month') is-invalid @enderror" id="harvest_month" name="harvest_month" type="month" value="{{ old('harvest_month', $schedule->harvest_month?->format('Y-m')) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="season">Season</label>
                            <select class="form-select" id="season" name="season" required>
                                <option value="">Choose season</option>
                                @foreach(\App\Models\FarmerPlantingSchedule::SEASONS as $season)
                                    <option value="{{ $season }}" @selected(old('season', $schedule->season) === $season)>{{ $season }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="farm_type">Type of farm</label>
                            <select class="form-select" id="farm_type" name="farm_type" required>
                                <option value="">Choose type of farm</option>
                                @foreach(\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)
                                    <option value="{{ $farmType }}" @selected(old('farm_type', $schedule->farm_type) === $farmType)>{{ $farmType }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="barangay">Barangay / farm location</label>
                            <select class="form-select" id="barangay" name="barangay">
                                <option value="">Not available</option>
                                @foreach($barangays as $barangay)<option value="{{ $barangay }}" @selected(old('barangay', $schedule->barangay) === $barangay)>{{ $barangay }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="farm_area_hectares">Farm size (hectares)</label>
                            <input class="form-control" id="farm_area_hectares" name="farm_area_hectares" type="number" min="0.01" max="10000" step="0.01" value="{{ old('farm_area_hectares', $schedule->farm_area_hectares) }}">
                            <div class="schedule-help mt-1">Needed to estimate your harvest. Leave blank if unknown.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="progress_status">Planting stage</label>
                            <select class="form-select" id="progress_status" name="progress_status">
                                <option value="">Automatic from dates</option>
                                @foreach(\App\Models\FarmerPlantingSchedule::MANUAL_STATUSES as $status)<option value="{{ $status }}" @selected(old('progress_status', $schedule->progress_status) === $status)>{{ $status }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="notes">Notes (optional)</label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="2000">{{ old('notes', $schedule->notes) }}</textarea>
                        </div>
                    </div>
                    <div class="d-flex flex-column flex-sm-row gap-2 mt-4">
                        <button class="btn btn-primary btn-lg" type="submit">Save schedule</button>
                        <a class="btn btn-outline-secondary btn-lg" href="{{ route('calendar.index') }}">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
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
                    const expected = season.value === 'Wet' ? [5,6,7] : (season.value === 'Dry' ? [10,11,12] : []);
                    if (plantingMonth && expected.length && !expected.includes(plantingMonth)) messages.push(`${season.value} season planting is normally ${season.value === 'Wet' ? 'May to July' : 'October to December'}.`);
                    const selectedHarvestMonth = monthOf(harvestDate.value || harvestMonth.value);
                    if (selectedHarvestMonth && ![3,4,10,11].includes(selectedHarvestMonth)) messages.push('Common harvest months are March, April, October, and November.');
                    warning.innerHTML = messages.length ? `<strong>Date warning:</strong> ${messages.join(' ')} You may still save this schedule.` : '';
                    warning.classList.toggle('d-none', messages.length === 0);
                };
                [planting, harvestDate, harvestMonth, season].forEach((field) => field?.addEventListener('change', review));
                review();
            })();
        </script>
    </div>
</x-app-layout>
