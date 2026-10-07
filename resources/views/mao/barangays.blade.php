<x-app-layout>
    <x-slot:title>Barangays</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 3: BarangayProfileController. A tab of Records. The heat map's
         Flood-prone, Drought-prone and MAO classification layers read what is saved here. --}}
    <div class="mfd itd itp" data-barangays-page>
        <x-it.page-header kicker="Records" title="Barangays" lede="Lian's 19 barangays: location, rice area and the MAO's risk classification. Flood- and drought-prone barangays show on the Heat Map." />

        @include('partials.manuscript-tabs', ['group' => 'records', 'label' => 'Records'])

        <div class="itp-grid mb-4" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));">
            <div class="mfd-card"><span class="small text-muted">Flood-prone</span><div class="h4 mb-0">{{ $summary['flood'] }}</div></div>
            <div class="mfd-card"><span class="small text-muted">Drought-prone</span><div class="h4 mb-0">{{ $summary['drought'] }}</div></div>
            <div class="mfd-card"><span class="small text-muted">Classified High risk</span><div class="h4 mb-0">{{ $summary['high'] }}</div></div>
            <div class="mfd-card"><span class="small text-muted">Rice area (MAO)</span><div class="h4 mb-0">{{ $summary['area'] > 0 ? rtrim(rtrim(number_format($summary['area'], 2), '0'), '.').' ha' : '—' }}</div></div>
        </div>

        <section class="itp-section" aria-labelledby="brgyTitle">
            <div class="itp-section-head">
                <h2 id="brgyTitle">All barangays</h2>
                <p>Open a barangay to edit it. "Registered" figures come from farmers' own farm details in iClimate.</p>
            </div>
            <div class="table-responsive mfd-card p-0">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr><th>Barangay</th><th>Location</th><th>Rice area (MAO)</th><th>Registered</th><th>Classification</th><th>Prone to</th><th class="text-end">Edit</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php $p = $row->profile; $slug = str($row->barangay)->slug(); @endphp
                            <tr id="b-{{ $slug }}" data-barangay-row="{{ $row->barangay }}">
                                <td><strong>{{ \App\Services\Risk\HeatMapPanel::displayName($row->barangay) }}</strong></td>
                                <td class="small">{{ $p->latitude !== null ? number_format((float) $p->latitude, 4).', '.number_format((float) $p->longitude, 4) : '—' }}</td>
                                <td>{{ $p->farm_area_hectares !== null ? rtrim(rtrim(number_format((float) $p->farm_area_hectares, 2), '0'), '.').' ha' : '—' }}</td>
                                <td class="small">{{ $row->farmers }} {{ \Illuminate\Support\Str::plural('farmer', $row->farmers) }}@if ($row->registered_area) · {{ rtrim(rtrim(number_format($row->registered_area, 2), '0'), '.') }} ha @endif</td>
                                <td>
                                    @if ($p->risk_classification)
                                        <span @class(['badge', 'text-bg-success' => $p->risk_classification === 'Low', 'text-bg-warning' => $p->risk_classification === 'Moderate', 'text-bg-danger' => $p->risk_classification === 'High'])>{{ $p->risk_classification }}</span>
                                    @else — @endif
                                </td>
                                <td class="small">{{ collect(['Flood' => $p->flood_prone, 'Drought' => $p->drought_prone])->filter()->keys()->implode(', ') ?: '—' }}</td>
                                <td class="text-end">
                                    <details>
                                        <summary class="btn btn-outline-primary btn-sm">Edit</summary>
                                        <form method="POST" action="{{ route('mao.barangays.update', $row->barangay) }}" class="text-start mt-2" style="min-width: 260px;" data-loading="true">
                                            @csrf @method('PUT')
                                            <div class="row g-2">
                                                <div class="col-6"><label class="form-label small">Latitude</label><input class="form-control form-control-sm" name="latitude" type="number" step="0.0000001" value="{{ $p->latitude }}"></div>
                                                <div class="col-6"><label class="form-label small">Longitude</label><input class="form-control form-control-sm" name="longitude" type="number" step="0.0000001" value="{{ $p->longitude }}"></div>
                                                <div class="col-6"><label class="form-label small">Rice area (ha)</label><input class="form-control form-control-sm" name="farm_area_hectares" type="number" step="0.01" min="0" value="{{ $p->farm_area_hectares }}"></div>
                                                <div class="col-6"><label class="form-label small">Classification</label>
                                                    <select class="form-select form-select-sm" name="risk_classification">
                                                        <option value="">Not classified</option>
                                                        @foreach ($classifications as $c)<option value="{{ $c }}" @selected($p->risk_classification === $c)>{{ $c }}</option>@endforeach
                                                    </select>
                                                </div>
                                                <div class="col-6"><label class="form-check small"><input class="form-check-input" type="checkbox" name="flood_prone" value="1" @checked($p->flood_prone)> Flood-prone</label></div>
                                                <div class="col-6"><label class="form-check small"><input class="form-check-input" type="checkbox" name="drought_prone" value="1" @checked($p->drought_prone)> Drought-prone</label></div>
                                                <div class="col-12"><label class="form-label small">Notes</label><textarea class="form-control form-control-sm" name="notes" rows="2" maxlength="2000">{{ $p->notes }}</textarea></div>
                                            </div>
                                            <button class="btn btn-primary btn-sm mt-2" type="submit" data-loading-text="Saving...">Save</button>
                                        </form>
                                    </details>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
