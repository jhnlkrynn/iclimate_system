{{-- Manuscript – Naicel (2026-10-08): the fields of a maintenance record (add and edit). --}}
<div class="row g-2">
    <div class="col-md-6"><label class="form-label small fw-semibold" for="{{ $prefix }}Title">Work done</label><input class="form-control form-control-sm" id="{{ $prefix }}Title" name="title" maxlength="255" required value="{{ $record?->title ?? old('title') }}"></div>
    <div class="col-md-3"><label class="form-label small fw-semibold" for="{{ $prefix }}Cat">Type</label>
        <select class="form-select form-select-sm" id="{{ $prefix }}Cat" name="category">@foreach (\App\Models\MaintenanceRecord::CATEGORIES as $key => $label)<option value="{{ $key }}" @selected(($record?->category ?? old('category')) === $key)>{{ $label }}</option>@endforeach</select>
    </div>
    <div class="col-md-3"><label class="form-label small fw-semibold" for="{{ $prefix }}Status">Status</label>
        <select class="form-select form-select-sm" id="{{ $prefix }}Status" name="status">@foreach (\App\Models\MaintenanceRecord::STATUSES as $key => $label)<option value="{{ $key }}" @selected(($record?->status ?? old('status', 'done')) === $key)>{{ $label }}</option>@endforeach</select>
    </div>
    <div class="col-md-4"><label class="form-label small fw-semibold" for="{{ $prefix }}When">When</label><input class="form-control form-control-sm" id="{{ $prefix }}When" type="datetime-local" name="performed_at" value="{{ $record?->performed_at?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('Y-m-d\TH:i') }}"></div>
    <div class="col-md-8"><label class="form-label small fw-semibold" for="{{ $prefix }}Desc">Details</label><textarea class="form-control form-control-sm" id="{{ $prefix }}Desc" name="description" rows="2" maxlength="5000">{{ $record?->description ?? old('description') }}</textarea></div>
</div>
