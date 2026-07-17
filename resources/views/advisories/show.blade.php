<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Advisory Details</div>
                <h1 class="h2 fw-bold mb-2">{{ $advisory->title }}</h1>
                <p class="mb-0 text-white-50">{{ $advisory->summary }}</p>
            </div>
            <div class="d-flex gap-2 action-cluster">
                <a class="btn btn-outline-light" href="{{ url()->previous() }}">Back</a>
                @if($canManage)
                    <a class="btn btn-light" href="{{ route('planting-advisories.edit', $advisory) }}">Edit</a>
                @endif
            </div>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card no-lift mb-4">
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge {{ $advisory->typeBadgeClass() }}">{{ $advisory->typeLabel() }}</span>
                        <span class="badge {{ $advisory->severityBadgeClass() }}">{{ $advisory->severityLabel() }}</span>
                        <span class="badge {{ $advisory->statusBadgeClass() }}">{{ $advisory->statusLabel() }}</span>
                    </div>
                    <h2 class="h5">Full Advisory</h2>
                    <p style="white-space: pre-line;">{{ $advisory->message ?: $advisory->content }}</p>
                    <h2 class="h5 mt-4">Recommended Action</h2>
                    <p style="white-space: pre-line;">{{ $advisory->recommended_action ?: 'Inspect actual field conditions and consult the Municipal Agriculture Office before acting.' }}</p>
                </div>
            </div>

            <div class="card no-lift">
                <div class="card-body">
                    <h2 class="h5">Weather Basis</h2>
                    @php($basis = $advisory->metadata['weather_basis'] ?? [])
                    <dl class="row mb-0 details-list g-3">
                        <dt class="col-sm-4">Expected rainfall</dt><dd class="col-sm-8">{{ data_get($basis, 'expected_rainfall_mm', 'N/A') }} mm</dd>
                        <dt class="col-sm-4">Rain probability</dt><dd class="col-sm-8">{{ data_get($basis, 'rain_probability_percent', 'N/A') }}%</dd>
                        <dt class="col-sm-4">Maximum temperature</dt><dd class="col-sm-8">{{ data_get($basis, 'maximum_temperature_c', 'N/A') }}&deg;C</dd>
                        <dt class="col-sm-4">Maximum wind speed</dt><dd class="col-sm-8">{{ data_get($basis, 'maximum_wind_speed_kmh', 'N/A') }} km/h</dd>
                        <dt class="col-sm-4">Forecast period</dt><dd class="col-sm-8">{{ data_get($basis, 'forecast_period', 'N/A') }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card no-lift mb-4">
                <div class="card-body">
                    <h2 class="h5">Location and Source</h2>
                    <div class="details-list">
                        <dt>Target location</dt><dd>{{ $advisory->targetLabel() }}</dd>
                        <dt>Source</dt><dd>{{ $advisory->source ?: 'Open-Meteo + iClimate Rules' }}</dd>
                        <dt>Generated date</dt><dd>{{ $advisory->created_at?->format('F d, Y g:i A') }}</dd>
                        <dt>Validity period</dt><dd>{{ $advisory->valid_from?->format('M d, Y g:i A') }} to {{ $advisory->valid_until?->format('M d, Y g:i A') }}</dd>
                        <dt>Review status</dt><dd>{{ $advisory->approved_at ? 'Approved by '.$advisory->approvedBy?->name : $advisory->statusLabel() }}</dd>
                    </div>
                </div>
            </div>
            <div class="alert alert-warning shadow-sm">
                <strong>Disclaimer.</strong>
                {{ $advisory->metadata['disclaimer'] ?? 'This advisory is generated from forecast data and iClimate decision-support rules. Actual farm conditions may differ. Consult the Municipal Agriculture Office and follow official PAGASA or local government warnings during severe weather.' }}
            </div>
            <div class="card no-lift">
                <div class="card-body">
                    <h2 class="h6 fw-bold">Official Advisories</h2>
                    <div class="d-grid gap-2">
                        <a class="btn btn-outline-primary btn-sm" href="https://www.pagasa.dost.gov.ph/" target="_blank" rel="noopener">PAGASA Website</a>
                        <a class="btn btn-outline-primary btn-sm" href="https://www.facebook.com/PAGASA.DOST.GOV.PH" target="_blank" rel="noopener">PAGASA Updates</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
