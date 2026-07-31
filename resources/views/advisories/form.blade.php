<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Advisory Editor</div>
                <h1 class="h2 fw-bold mb-2">{{ $advisory->exists ? 'Edit Advisory' : 'Create Advisory' }}</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Manual MAO-reviewed advisories can be used alongside generated guidance.</p>
            </div>
            <a class="btn btn-outline-light" href="{{ route('planting-advisories.index') }}">Back</a>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body">
            <form method="POST" action="{{ $action }}" data-loading="true">
                @csrf
                @if($method !== 'POST') @method($method) @endif
                <div class="row g-3">
                    <div class="col-lg-8">
                        <label class="form-label">Title</label>
                        <input class="form-control" name="title" value="{{ old('title', $advisory->title) }}" required>
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="advisory_type" required>
                            @foreach($types as $type)
                                <option value="{{ $type }}" @selected(old('advisory_type', $advisory->advisory_type ?: strtolower((string) $advisory->type)) === $type)>{{ str($type)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="5" required>{{ old('message', $advisory->message ?: $advisory->content) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Recommended Action</label>
                        <textarea class="form-control" name="recommended_action" rows="3">{{ old('recommended_action', $advisory->recommended_action) }}</textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Barangay</label>
                        <select class="form-select" name="target_barangay">
                            <option value="">All Barangays</option>
                            @foreach($barangays as $barangay)
                                <option value="{{ $barangay }}" @selected(old('target_barangay', $advisory->target_barangay) === $barangay)>{{ $barangay }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Severity</label>
                        <select class="form-select" name="severity">
                            @foreach($severities as $severity)
                                <option value="{{ $severity }}" @selected(old('severity', $advisory->severity ?: 'information') === $severity)>{{ str($severity)->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            @foreach($statuses as $status)
                                <option value="{{ $status }}" @selected(old('status', $advisory->status ?: 'pending_review') === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valid From</label>
                        <input class="form-control" type="datetime-local" name="valid_from" value="{{ old('valid_from', $advisory->valid_from?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Valid Until</label>
                        <input class="form-control" type="datetime-local" name="valid_until" value="{{ old('valid_until', $advisory->valid_until?->format('Y-m-d\TH:i')) }}">
                    </div>
                    <div class="col-12">
                        <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save Advisory</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
