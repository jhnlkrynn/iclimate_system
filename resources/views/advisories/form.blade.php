<x-app-layout>
    {{-- The MAO's advisory form (2026-10), in the order it is filled in: Barangay, Recommendation, What to do, Valid
         until, Severity, then the optional MAO Activity. A new advisory opens already filled in from the system's
         recommendation for the barangay (this week's Heat Map rating), so the MAO reviews, then approves and publishes. --}}
    @php
        $type = old('advisory_type', $advisory->advisory_type ?: (strtolower((string) $advisory->type) ?: 'planting'));
        $severity = old('severity', $advisory->severity ?: 'low');
        $severities = ['low' => 'Low (green)', 'moderate' => 'Moderate (yellow)', 'high' => 'High (red)'];
        // A severity saved by the system that the form does not offer stays selectable, so editing never changes it silently.
        if (! isset($severities[$severity])) {
            $severities = [$severity => str($severity)->headline()->toString()] + $severities;
        }
        $pending = ! $advisory->exists || $advisory->status === \App\Models\PlantingAdvisory::STATUS_PENDING_REVIEW;
        $activity = old('mao_activity', data_get($advisory->metadata, 'mao_activity'));
        $activityDate = old('mao_activity_date', data_get($advisory->metadata, 'mao_activity_date'));
    @endphp
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Advisories</div>
                <h1 class="h2 fw-bold mb-2">{{ $advisory->exists ? ($pending ? 'Review Advisory' : 'Edit Advisory') : 'New Advisory' }}</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Review the recommendation, change what is needed, then approve and publish it to farmers.</p>
            </div>
            <a class="btn btn-outline-light" href="{{ route('planting-advisories.index') }}">Back</a>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif
            <p class="small text-muted" data-adv-prefill-note @if (! $prefilled) hidden @endif>
                Filled in from this week's saved forecast. Please review and edit it before publishing.
            </p>
            <form method="POST" action="{{ $action }}" data-loading="true" data-adv-form>
                @csrf
                @if($method !== 'POST') @method($method) @endif
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="advFormBarangay">1. Barangay</label>
                        <select class="form-select" id="advFormBarangay" name="target_barangay" data-adv-barangay>
                            <option value="">All Barangays (all of Lian)</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay }}" @selected(old('target_barangay', $advisory->target_barangay) === $barangay)>{{ $barangay }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($type !== 'planting')
                        {{-- Planting is the usual kind. Another kind is shown only for an advisory that already has it. --}}
                        <div class="col-md-6">
                            <label class="form-label" for="advFormType">Kind of advisory</label>
                            <select class="form-select" id="advFormType" name="advisory_type">
                                @foreach(array_unique(['planting', $type]) as $option)
                                    <option value="{{ $option }}" @selected($type === $option)>{{ str($option)->headline() }}</option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <input type="hidden" name="advisory_type" value="planting">
                    @endif
                    <div class="col-12">
                        <label class="form-label" for="advFormRecommendation">2. Recommendation <span class="text-muted fw-normal">(one sentence)</span></label>
                        <textarea class="form-control" id="advFormRecommendation" name="message" rows="2" maxlength="2000" required data-adv-recommendation>{{ old('message', $advisory->message ?: $advisory->content) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="advFormAction">3. What to do</label>
                        <textarea class="form-control" id="advFormAction" name="recommended_action" rows="2" maxlength="2000" data-adv-action>{{ old('recommended_action', $advisory->recommended_action) }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="advFormUntil">4. Valid until</label>
                        <input class="form-control" id="advFormUntil" type="date" name="valid_until" required
                            value="{{ old('valid_until', $advisory->valid_until?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('Y-m-d')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="advFormSeverity">5. Severity</label>
                        <select class="form-select" id="advFormSeverity" name="severity" data-adv-severity>
                            @foreach($severities as $value => $label)
                                <option value="{{ $value }}" @selected($severity === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="advFormActivity">MAO Activity <span class="text-muted fw-normal">(optional)</span></label>
                        <input class="form-control" id="advFormActivity" name="mao_activity" maxlength="255" value="{{ $activity }}" placeholder="Example: Seed distribution at the barangay hall">
                        <div class="form-text">Shown with the advisory and on the Calendar.</div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="advFormActivityDate">Activity date</label>
                        <input class="form-control" id="advFormActivityDate" type="date" name="mao_activity_date" value="{{ $activityDate }}">
                    </div>
                    <div class="col-12 d-flex flex-wrap gap-2">
                        @if ($pending)
                            <button class="btn btn-primary" type="submit" name="status" value="published" data-loading-text="Publishing...">Approve and Publish</button>
                            <button class="btn btn-outline-secondary" type="submit" name="status" value="pending_review" data-loading-text="Saving...">Save for Review</button>
                        @else
                            <button class="btn btn-primary" type="submit" name="status" value="{{ $advisory->status }}" data-loading-text="Saving...">Save Changes</button>
                            @if ($advisory->status !== \App\Models\PlantingAdvisory::STATUS_PUBLISHED)
                                <button class="btn btn-outline-primary" type="submit" name="status" value="published" data-loading-text="Publishing...">Publish</button>
                            @endif
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>

    @if ($suggestions)
        {{-- Choosing another barangay fills in its recommendation, unless the text was already changed by hand. --}}
        <script type="application/json" data-adv-suggestions>@json($suggestions)</script>
        <script>
            (() => {
                const form = document.querySelector('[data-adv-form]');
                const data = JSON.parse(document.querySelector('[data-adv-suggestions]').textContent || '{}');
                const field = (name) => form.querySelector(`[data-adv-${name}]`);
                const note = document.querySelector('[data-adv-prefill-note]');
                let filled = { recommendation: field('recommendation').value, action: field('action').value };
                field('barangay').addEventListener('change', (event) => {
                    const suggestion = data[event.target.value];
                    const untouched = field('recommendation').value === filled.recommendation && field('action').value === filled.action;
                    if (!suggestion || !untouched) return;
                    field('recommendation').value = suggestion.recommendation;
                    field('action').value = suggestion.action;
                    field('severity').value = suggestion.severity;
                    filled = { recommendation: suggestion.recommendation, action: suggestion.action };
                    note.hidden = false;
                });
            })();
        </script>
    @endif
</x-app-layout>
