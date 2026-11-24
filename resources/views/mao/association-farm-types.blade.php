<x-app-layout>
    <x-slot:title>Association Farm Types</x-slot:title>
    <div class="mfd itd itp">
        <x-shared.page-header kicker="Municipal Agriculture Office" title="Association Farm Types" lede="Confirm or update each farmers' association's water source (Irrigated or Rainfed). Confirming clears the inferred federation note." />

        @include('partials.manuscript-tabs', ['group' => 'farmers', 'label' => 'Farmers'])

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
        @endif

        <section class="mfd-card mb-4" aria-labelledby="yieldOverrideTitle">
            <h2 id="yieldOverrideTitle" class="h5 mb-2">Rainfed vs. Irrigated Yield Difference (Optional MAO Override)</h2>
            <p class="small text-muted mb-3">
                iClimate harvest records so far: <strong>Rainfed {{ $farmTypeStats['rainfed_count'] ?? 0 }} / {{ $farmTypeStats['min_harvests'] ?? 10 }}</strong> ({{ $farmTypeStats['rainfed_seasons'] ?? 0 }} / 2 seasons) ·
                <strong>Irrigated {{ $farmTypeStats['irrigated_count'] ?? 0 }} / {{ $farmTypeStats['min_harvests'] ?? 10 }}</strong> ({{ $farmTypeStats['irrigated_seasons'] ?? 0 }} / 2 seasons).
                @if (($farmTypeStats['source'] ?? 'none') === 'records')
                    Computed factors from iClimate harvest records are active and take priority over any MAO override.
                @else
                    Until enough harvests are recorded, MAO may enter a rainfed yield difference (%) from MAO records (labeled <em>Set by MAO</em>).
                @endif
            </p>
            <form method="POST" action="{{ route('mao.associations.yield-override.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label for="rainfedDiffPercent" class="form-label small">Rainfed difference vs. Irrigated (%)</label>
                    <input id="rainfedDiffPercent" type="number" step="0.1" min="-30" max="30" name="rainfed_diff_percent" class="form-control form-control-sm" value="{{ old('rainfed_diff_percent', $override?->rainfed_diff_percent) }}" placeholder="e.g. -15" required>
                </div>
                <div class="col-md-6">
                    <label for="overrideNote" class="form-label small">Source / Note (required)</label>
                    <input id="overrideNote" type="text" maxlength="500" name="note" class="form-control form-control-sm" value="{{ old('note', $override?->note) }}" placeholder="e.g. MAO 2025 field survey summary" required>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm">Save override</button>
                </div>
            </form>
            @if ($override)
                <form method="POST" action="{{ route('mao.associations.yield-override.destroy') }}" class="mt-2">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Clear MAO override</button>
                </form>
            @endif
        </section>

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
