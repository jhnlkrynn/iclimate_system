<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Details</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }} Details</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Record no. {{ $record->id }}</p>
            </div>
            <div class="d-flex gap-2 align-self-start align-self-lg-end action-cluster">
                <a class="btn btn-outline-secondary" href="{{ route($routeName.'.index') }}">Back</a>
                @php $deletedAccount = $routeName === 'users' && $record->isDeleted(); @endphp
                @if ($canManage && ! $deletedAccount)
                    <a class="btn btn-light" href="{{ route($routeName.'.edit', $record) }}">Edit</a>
                @endif
                {{-- Manuscript – Naicel: IT resets a user's password to a one-time temporary password. --}}
                @if ($routeName === 'users' && $canManage && ! $deletedAccount && ! $record->is(auth()->user()))
                    <form method="POST" action="{{ route('users.reset-password', $record) }}" data-loading="true" data-ic-confirm="Give {{ $record->name }} a new temporary password? Their current password stops working.">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit" data-loading-text="Resetting...">Reset Password</button>
                    </form>
                @endif
                {{-- End Manuscript – Naicel --}}
            </div>
        </div>
    </section>

    {{-- Manuscript – Naicel: the temporary password is shown once, right after a reset. --}}
    @if (session('temporary_password'))
        <div class="alert alert-warning" role="status" data-temporary-password>
            New temporary password for {{ $record->name }}: <strong class="font-monospace">{{ session('temporary_password') }}</strong>.
            Give it to them in person; it is shown only once.
        </div>
    @endif

    <div class="card no-lift">
        <div class="card-body">
            <dl class="row mb-0 details-list g-3">
                @foreach ($columns as $field => $label)
                    <dt class="col-sm-3">{{ $label }}</dt>
                    <dd class="col-sm-9">{{ filled(data_get($record, $field)) ? data_get($record, $field) : 'Not set' }}</dd>
                @endforeach
                <dt class="col-sm-3">Created</dt>
                <dd class="col-sm-9">{{ $record->created_at ?? 'Not set' }}</dd>
                <dt class="col-sm-3">Updated</dt>
                <dd class="col-sm-9">{{ $record->updated_at ?? 'Not set' }}</dd>
            </dl>
            @include('partials.record-stamps', ['record' => $record])
        </div>
    </div>

    @if ($routeName === 'users')
        @include('users._account-details', ['user' => $record])
    @endif
    @if ($routeName === 'farmer-profiles' && ($archivedFarms = $record->farms->whereNotNull('archived_at')->sortByDesc('archived_at')->values())->isNotEmpty())
        {{-- Archived farms (removed by the farmer, or archived when the account was closed): MAO / IT can bring one back. --}}
        <div class="card no-lift mt-4" data-archived-farms>
            <div class="card-header border-0"><div class="fw-semibold">Archived farms ({{ $archivedFarms->count() }})</div></div>
            <ul class="list-group list-group-flush">
                @foreach ($archivedFarms as $farm)
                    <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <span><strong>{{ $farm->farm_name }}</strong> <span class="text-muted">· {{ $farm->barangay ?: 'No barangay' }} · archived {{ $farm->archived_at?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y') }}</span></span>
                        @if ($canManage)
                            <form method="POST" action="{{ route('farmer-profiles.farms.restore', ['farmerProfile' => $record->id, 'farm' => $farm->id]) }}" data-loading="true">
                                @csrf
                                <button class="btn btn-sm btn-outline-success" type="submit" data-restore-farm="{{ $farm->id }}" data-loading-text="Restoring...">Restore</button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($routeName === 'farmer-profiles' && ($staffFarms = $record->farms->whereNull('archived_at')->values())->isNotEmpty())
        {{-- Each of the farmer's farms with its association (farms.farmer_organization_id). --}}
        @php $associationNames = \App\Services\Farms\FarmAssociations::available() ? \App\Services\Farms\FarmAssociations::namesFor($staffFarms) : null; @endphp
        <div class="card no-lift mt-4" data-farmer-farms>
            <div class="card-header border-0"><div class="fw-semibold">{{ $staffFarms->count() === 1 ? 'Farm' : 'Farms ('.$staffFarms->count().')' }}</div></div>
            <ul class="list-group list-group-flush">
                @foreach ($staffFarms as $farm)
                    <li class="list-group-item">
                        <strong>{{ $farm->farm_name }}</strong>
                        <span class="text-muted"> · {{ implode(' · ', array_filter([$farm->barangay, $farm->farm_type])) }}</span>
                        @if ($associationNames !== null)<div class="small" data-farm-association="{{ $farm->associationState() }}">{{ \App\Services\Farms\FarmAssociations::staffLabel($farm, $associationNames) }}</div>@endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($routeName === 'farmer-profiles' && $record->farmBoundary)
        @vite('resources/css/leaflet.css')
        <div class="card no-lift mt-4">
            <div class="card-header border-0">
                <div class="fw-semibold">Mapped Farm Boundary</div>
                <div class="small text-muted">Exact coordinates are restricted to authorized staff.</div>
            </div>
            <div class="card-body">
                <div id="staffBoundaryMap" style="height: 430px; border-radius: 10px; overflow: hidden;"></div>
                <div class="row g-3 mt-1">
                    <div class="col-sm-6"><strong>{{ number_format((float) ($record->farmBoundary->calculated_area_hectares ?? 0), 4) }} ha</strong><div class="small text-muted">Calculated mapped area</div></div>
                    <div class="col-sm-6"><strong>{{ number_format((float) ($record->farmBoundary->calculated_perimeter_meters ?? 0), 2) }} m</strong><div class="small text-muted">Calculated perimeter</div></div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', async () => {
                // app.js loads Leaflet lazily; wait for it instead of racing it.
                try { await window.iClimateVisualsReady; } catch (e) { /* no map */ }
                const Leaflet = window.L;
                if (!Leaflet) return;
                const mapEl = document.getElementById('staffBoundaryMap');
                if (!mapEl) return;
                const points = @js($record->farmBoundary->boundary_coordinates);
                if (!Array.isArray(points) || points.length < 3) return;
                const latLngs = points.filter(p => p && typeof p.lat === 'number' && typeof p.lng === 'number').map((point) => [point.lat, point.lng]);
                if (latLngs.length < 3) return;
                const map = Leaflet.map('staffBoundaryMap', { scrollWheelZoom: false, dragging: false }).setView([14.033, 120.650], 13);
                const satelliteLayer = Leaflet.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles &copy; Esri', maxZoom: 19 }).addTo(map);
                const streetLayer = Leaflet.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '&copy; OpenStreetMap contributors', maxZoom: 19 });
                Leaflet.control.layers({ 'Satellite imagery': satelliteLayer, 'Street map': streetLayer }, null, { position: 'topright', collapsed: false }).addTo(map);
                const polygon = Leaflet.polygon(latLngs, { color: '#1f5a46', fillColor: '#52b788', fillOpacity: .28, weight: 3 }).addTo(map);
                const bounds = polygon.getBounds();
                if (bounds && typeof bounds.isValid === 'function' && bounds.isValid()) {
                    map.fitBounds(bounds, { padding: [24, 24] });
                }
            });
        </script>
    @elseif ($routeName === 'farmer-profiles')
        <div class="alert alert-secondary mt-4">This farmer has not saved a farm boundary.</div>
    @endif
</x-app-layout>
