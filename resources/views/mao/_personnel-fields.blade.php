{{-- The fields of an MAO account (panel revision 4.4), for the edit and add forms of mao/personnel. --}}
@php
    $key = $person?->id ?? 'new';
    $covered = $person ? $person->maoBarangays->keyBy('barangay') : collect();
    $isHead = $person?->isMaoHead() ?? false;
@endphp
<div class="pr-grid">
    <div>
        <label class="form-label" for="name-{{ $key }}">Full name</label>
        <input class="form-control" id="name-{{ $key }}" name="name" value="{{ old('name', $person?->name) }}" required maxlength="255">
    </div>
    <div>
        <label class="form-label" for="position-{{ $key }}">Position</label>
        <input class="form-control" id="position-{{ $key }}" name="position" value="{{ old('position', $person?->position) }}" required maxlength="160" list="maoPositions">
    </div>
    <div>
        <label class="form-label" for="station-{{ $key }}">Station</label>
        <select class="form-select" id="station-{{ $key }}" name="station_type" required>
            @foreach (\App\Models\User::STATION_TYPES as $station)
                <option value="{{ $station }}" @selected(old('station_type', $person?->station_type ?? \App\Models\User::STATION_MUNICIPAL) === $station)>{{ $station }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="form-label" for="area-{{ $key }}">Area covered (ha)</label>
        <input class="form-control" id="area-{{ $key }}" name="area_covered_ha" type="number" step="0.01" min="0" inputmode="decimal" value="{{ old('area_covered_ha', $person?->area_covered_ha) }}">
    </div>
</div>
@if ($isHead)
    <p class="pr-note mt-3">The Municipal Agriculturist covers all barangays of Lian.</p>
@else
    <fieldset class="pr-barangays mt-3">
        <legend class="form-label">Barangays covered</legend>
        <p class="small text-muted mb-2">Tick each barangay. Add a sitio only when the person covers just part of it (e.g. Tan-ag of San Diego).</p>
        <div class="pr-check-grid">
            @foreach (\App\Support\LianBarangays::all() as $barangay)
                @php $row = $covered->get($barangay); $id = 'brgy-'.$key.'-'.\Illuminate\Support\Str::slug($barangay); @endphp
                <div class="pr-check">
                    <label for="{{ $id }}">
                        <input type="checkbox" id="{{ $id }}" name="barangays[]" value="{{ $barangay }}" @checked($row !== null)>
                        <span>{{ $barangay }}</span>
                    </label>
                    <input class="form-control form-control-sm" name="sitios[{{ $barangay }}]" value="{{ $row?->sitio }}" placeholder="Sitio (optional)" aria-label="Sitio of {{ $barangay }} (optional)" maxlength="120">
                </div>
            @endforeach
        </div>
    </fieldset>
@endif
