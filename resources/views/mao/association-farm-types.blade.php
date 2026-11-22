<x-app-layout>
    <x-slot:title>Association Farm Types</x-slot:title>
    <div class="mfd itd itp">
        <x-shared.page-header kicker="Municipal Agriculture Office" title="Association Farm Types" lede="Confirm or update each farmers' association's water source (Irrigated or Rainfed). Confirming clears the inferred federation note." />

        @include('partials.manuscript-tabs', ['group' => 'farmers', 'label' => 'Farmers'])

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        <form class="itd-filters mb-3" method="GET" action="{{ route('mao.associations.farm-types.index') }}">
            <label>Barangay
                <select name="barangay" data-autosubmit>
                    <option value="">All</option>
                    @foreach ($barangays as $name)
                        <option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label>Status
                <select name="status" data-autosubmit>
                    <option value="">All</option>
                    <option value="pending" @selected($status === 'pending')>Pending confirmation ({{ $pendingCount }})</option>
                    <option value="confirmed" @selected($status === 'confirmed')>Confirmed</option>
                </select>
            </label>
            <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
        </form>

        <section class="itp-section" aria-labelledby="aftTitle">
            <div class="itp-section-head">
                <h2 id="aftTitle">Associations <span class="ic-tab-count">{{ $associations->count() }}</span></h2>
                <p>{{ $pendingCount }} pending MAO confirmation.</p>
            </div>

            @if ($associations->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No associations match the selected filters.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-association-farm-types>
                        <thead>
                            <tr>
                                <th>Barangay</th>
                                <th>Association</th>
                                <th>Registration</th>
                                <th class="text-end">Area (ha)</th>
                                <th class="text-end">Members</th>
                                <th>Status</th>
                                <th class="text-end">Confirm / Change</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($associations as $association)
                                <tr data-association-id="{{ $association->id }}">
                                    <td>{{ $association->barangay }}</td>
                                    <td><strong>{{ $association->name }}</strong></td>
                                    <td>
                                        <div>{{ $association->registration_no ?: '—' }}</div>
                                        @if ($association->date_registered)
                                            <div class="small text-muted">{{ $association->date_registered->format('M j, Y') }}</div>
                                        @endif
                                    </td>
                                    <td class="text-end">{{ $association->area_hectares !== null ? number_format((float) $association->area_hectares, 2) : '—' }}</td>
                                    <td class="text-end">{{ $association->farmer_count !== null ? number_format((int) $association->farmer_count) : '—' }}</td>
                                    <td>
                                        @if ($association->farm_type_source)
                                            <span class="badge bg-warning-subtle text-warning-emphasis">{{ $association->farm_type_source }}</span>
                                        @elseif ($association->farm_type)
                                            <span class="badge bg-success-subtle text-success-emphasis">Confirmed</span>
                                        @else
                                            <span class="text-muted">Unset</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('mao.associations.farm-types.update', $association) }}" class="d-inline-flex gap-2 align-items-center justify-content-end">
                                            @csrf
                                            <select name="farm_type" class="form-select form-select-sm" aria-label="Farm type for {{ $association->name }}" style="width: auto;">
                                                <option value="Irrigated" @selected($association->farm_type === 'Irrigated')>Irrigated</option>
                                                <option value="Rainfed" @selected($association->farm_type === 'Rainfed')>Rainfed</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">Confirm</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
