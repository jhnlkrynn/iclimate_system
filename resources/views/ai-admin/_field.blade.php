@php
    $type = $field['type'];
    $wide = in_array($type, ['textarea', 'list'], true);
@endphp

<div class="{{ $wide ? 'col-12' : 'col-12 col-md-4' }}">
    @if ($type === 'bool')
        <div class="form-check mt-4">
            <input type="hidden" name="{{ $name }}" value="0">
            <input class="form-check-input" type="checkbox" id="{{ $id }}" name="{{ $name }}" value="1" @checked($value)>
            <label class="form-check-label" for="{{ $id }}">{{ $field['label'] }}</label>
        </div>
    @else
        <label class="form-label small mb-1" for="{{ $id }}">{{ $field['label'] }}</label>
        @if ($type === 'select')
            <select class="form-select form-select-sm" id="{{ $id }}" name="{{ $name }}">
                @if (in_array('nullable', $field['rules'] ?? [], true))
                    <option value="">None</option>
                @endif
                @foreach (\App\Support\AiAdminResources::options($field) as $optionValue => $optionLabel)
                    <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                @endforeach
            </select>
        @elseif ($type === 'textarea' || $type === 'list')
            <textarea class="form-control form-control-sm" id="{{ $id }}" name="{{ $name }}" rows="{{ $type === 'list' ? 3 : 2 }}">{{ $value }}</textarea>
        @else
            <input class="form-control form-control-sm" type="text" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}">
        @endif
    @endif
</div>
