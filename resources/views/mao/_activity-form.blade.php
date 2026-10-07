{{-- The Add / Edit Activity fields of the MAO Planner. $activity is null when adding; $prefix keeps the field ids unique. --}}
@php $old = fn (string $field, $default = null) => $activity ? ($activity->{$field} instanceof \DateTimeInterface ? $activity->{$field}->format('Y-m-d') : $activity->{$field}) : old($field, $default); @endphp
<form class="mpl-form" method="POST" action="{{ $activity ? route('mao.planner.update', $activity) : route('mao.planner.store') }}" data-loading="true">
    @csrf
    @if ($activity) @method('PUT') @endif
    <div class="mpl-field">
        <label for="{{ $prefix }}Type">Activity type</label>
        <select id="{{ $prefix }}Type" name="activity_type" class="form-select" required>
            @foreach ($types as $type)<option value="{{ $type }}" @selected($old('activity_type') === $type)>{{ $type }}</option>@endforeach
        </select>
    </div>
    <div class="mpl-field">
        <label for="{{ $prefix }}Barangay">Barangay</label>
        <select id="{{ $prefix }}Barangay" name="barangay" class="form-select">
            <option value="">All Barangays</option>
            @foreach ($barangays as $barangay)<option value="{{ $barangay }}" @selected($old('barangay') === $barangay)>{{ $barangay }}</option>@endforeach
        </select>
    </div>
    <div class="mpl-field">
        <label for="{{ $prefix }}Start">Date</label>
        <input id="{{ $prefix }}Start" type="date" name="starts_on" class="form-control" value="{{ $old('starts_on') }}" required>
    </div>
    <div class="mpl-field">
        <label for="{{ $prefix }}End">End date (optional)</label>
        <input id="{{ $prefix }}End" type="date" name="ends_on" class="form-control" value="{{ $old('ends_on') }}">
    </div>
    <div class="mpl-field is-wide">
        <label for="{{ $prefix }}Title">Title</label>
        <input id="{{ $prefix }}Title" type="text" name="title" class="form-control" maxlength="160" value="{{ $old('title') }}" placeholder="Example: Community planting day" required>
    </div>
    <div class="mpl-field is-wide">
        <label for="{{ $prefix }}Notes">Notes (optional)</label>
        <textarea id="{{ $prefix }}Notes" name="notes" class="form-control" rows="3" maxlength="2000">{{ $old('notes') }}</textarea>
    </div>
    <div class="mpl-field is-wide">
        <button class="btn btn-primary" type="submit" data-loading-text="Saving...">{{ $activity ? 'Save changes' : 'Add Activity' }}</button>
    </div>
</form>
