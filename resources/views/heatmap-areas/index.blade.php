<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Barangay Risk Map</div>
                <h1 class="h2 fw-bold mb-2">Climate Risk Management</h1>
                <p class="mb-0 text-white-50">Risk cards for Flood, Drought, Typhoon, and Heat. GIS integration is not enabled yet.</p>
            </div>
            @if ($canManage)
                <a class="btn btn-light align-self-start align-self-lg-end" href="{{ route('heatmap-areas.create') }}">Create Risk Area</a>
            @endif
        </div>
    </section>

    <div class="card filter-panel no-lift mb-4">
        <div class="card-body">
            <form class="row g-3 align-items-end" method="GET" data-loading="true">
                <div class="col-lg-4"><label class="form-label fw-semibold">Search</label><input class="form-control form-control-lg" name="search" value="{{ $search }}" placeholder="Search barangay or risk"></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label fw-semibold">Risk Level</label><select class="form-select form-select-lg" name="risk_level"><option value="">All</option>@foreach($riskLevels as $level)<option value="{{ $level }}" @selected(request('risk_level') === $level)>{{ $level }}</option>@endforeach</select></div>
                <div class="col-sm-6 col-lg-3"><label class="form-label fw-semibold">Risk Type</label><select class="form-select form-select-lg" name="risk_type"><option value="">All</option>@foreach($riskTypes as $type)<option value="{{ $type }}" @selected(request('risk_type') === $type)>{{ $type }}</option>@endforeach</select></div>
                <div class="col-lg-auto d-flex gap-2"><button class="btn btn-outline-primary btn-lg" type="submit" data-loading-text="Filtering...">Apply</button><a class="btn btn-outline-secondary btn-lg" href="{{ route('heatmap-areas.index') }}">Reset</a></div>
            </form>
        </div>
    </div>

    @if ($records->count())
        <div class="row g-4">
            @foreach ($records as $record)
                @php
                    $levelClass = ['Low' => 'success', 'Moderate' => 'primary', 'High' => 'warning', 'Severe' => 'danger'][$record->risk_level] ?? 'secondary';
                    $typeClass = ['Flood' => 'primary', 'Drought' => 'warning', 'Typhoon' => 'secondary', 'Heat' => 'danger'][$record->risk_type] ?? 'secondary';
                @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card risk-card h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-3 mb-3">
                                <h2 class="h5 mb-0">{{ $record->barangay }}</h2>
                                <span class="badge text-bg-{{ $levelClass }}">{{ $record->risk_level }}</span>
                            </div>
                            <span class="badge text-bg-{{ $typeClass }} mb-3">{{ $record->risk_type }}</span>
                            <p class="text-muted">{{ $record->description ?: 'No description provided.' }}</p>
                            <div class="d-flex gap-2 flex-wrap action-cluster">
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('heatmap-areas.show', $record) }}">View</a>
                                @if ($canManage)
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('heatmap-areas.edit', $record) }}">Edit</a>
                                    <form method="POST" action="{{ route('heatmap-areas.destroy', $record) }}" data-loading="true" onsubmit="return confirm('Delete this risk area?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" type="submit" data-loading-text="Deleting...">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <div class="mt-4 d-flex justify-content-end">{{ $records->links() }}</div>
    @else
        <div class="empty-state text-center p-5"><div class="h5">No heat map risk records found</div><div class="text-muted">Try changing filters or create a new risk area.</div></div>
    @endif
</x-app-layout>