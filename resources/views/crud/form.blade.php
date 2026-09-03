<x-app-layout>
    <section class="page-hero">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-end">
            <div>
                <div class="eyebrow mb-2">Record Editor</div>
                <h1 class="h2 fw-bold mb-2">{{ $title }}</h1>
                <p class="mb-0" style="color: var(--ic-ink-mid);">Complete the form and save changes.</p>
            </div>
            <a class="btn btn-light align-self-start align-self-lg-end" href="{{ route($routeName.'.index') }}">Back to List</a>
        </div>
    </section>

    <div class="card no-lift">
        <div class="card-body p-4">
            <form method="POST" action="{{ $action }}" data-loading="true">
                @csrf
                @if ($method !== 'POST')
                    @method($method)
                @endif

                <div class="row g-3">
                    @foreach ($fields as $field)
                        @php
                            $name = $field['name'];
                            $type = $field['type'] ?? 'text';
                            $value = old($name, $record ? data_get($record, $name) : '');
                            if (is_array($value)) { $value = json_encode($value, JSON_PRETTY_PRINT); }
                        @endphp
                        <div class="col-md-{{ $type === 'textarea' ? '12' : '6' }}">
                            <div class="soft-section p-3 h-100">
                                <label class="form-label fw-semibold" for="{{ $name }}">{{ $field['label'] }}</label>
                                @if ($type === 'textarea')
                                    <textarea class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" rows="5">{{ $value }}</textarea>
                                @elseif ($type === 'select')
                                    <select class="form-select @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}">
                                        <option value="">Select {{ $field['label'] }}</option>
                                        @foreach (($field['options'] ?? []) as $optionValue => $optionLabel)
                                            <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($type === 'checkbox')
                                    <div class="form-check form-switch mt-2">
                                        <input type="hidden" name="{{ $name }}" value="0">
                                        <input class="form-check-input @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1" @checked((bool) $value)>
                                        <label class="form-check-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                    </div>
                                @else
                                    <input class="form-control @error($name) is-invalid @enderror" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $field['step'] ?? '' }}" value="{{ $type === 'password' ? '' : $value }}">
                                @endif
                                @error($name)
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-column flex-sm-row gap-2 mt-4 action-cluster align-self-start">
                    <button class="btn btn-primary" type="submit" data-loading-text="Saving...">Save Changes</button>
                    <a class="btn btn-outline-secondary" href="{{ route($routeName.'.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>