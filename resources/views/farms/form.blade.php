<x-app-layout>
    {{-- Add Farm / Edit Farm (FarmController): name, barangay and sitio, farm type, and the farm's size in the way the
         farmer knows it (exact, estimated range, measured on the map, or not yet). Same look as the other farmer pages. --}}
    @php
        $editing = $farm->exists;
        $sizeMethod = old('farm_size_method', $farm->farm_size_method ?: \App\Models\Farm::SIZE_UNKNOWN);
        $unit = old('farm_size_unit', $farm->farm_size_unit === \App\Models\Farm::UNIT_SQUARE_METERS ? 'square_meters' : 'hectares');
        $value = old('farm_size_value', $farm->farm_size_method === \App\Models\Farm::SIZE_EXACT && $farm->farm_size_hectares
            ? ($unit === 'square_meters' ? rtrim(rtrim(number_format((float) $farm->farm_size_hectares * 10000, 2, '.', ''), '0'), '.') : rtrim(rtrim(number_format((float) $farm->farm_size_hectares, 4, '.', ''), '0'), '.'))
            : '');
    @endphp

    <div class="fad frec ffm" data-farm-form>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farms.title') }}</span>
                <h1>{{ $editing ? __('farms.form.edit_title', ['name' => $farm->farm_name]) : __('farms.form.add_title') }}</h1>
                <p>{{ __('farms.form.intro') }}</p>
            </div>
        </header>

        <form method="POST" action="{{ $action }}" class="frec-panel ffm-form" novalidate>
            @csrf
            @if ($method !== 'POST') @method($method) @endif

            @if ($errors->any())
                <div class="frec-note is-danger" role="alert">
                    @foreach ($errors->all() as $message)<p class="mb-0">{{ $message }}</p>@endforeach
                </div>
            @endif

            <div class="fad-field">
                <label class="fad-label" for="farm_name">{{ __('farms.form.name') }}</label>
                <input class="fad-input" id="farm_name" name="farm_name" type="text" maxlength="80" value="{{ old('farm_name', $editing ? $farm->farm_name : '') }}" placeholder="{{ $defaultName }}" aria-describedby="farmNameHint">
                <span class="fad-m-small" id="farmNameHint">{{ __('farms.form.name_hint', ['default' => $defaultName]) }}</span>
            </div>

            <fieldset class="ffm-group">
                <legend class="fad-label">{{ __('farms.form.location') }}</legend>
                <div class="ffm-grid">
                    <div class="fad-field">
                        <label class="fad-m-field-label" for="barangay">{{ __('farms.form.barangay') }}</label>
                        <select class="fad-input" id="barangay" name="barangay" required @error('barangay') aria-invalid="true" @enderror>
                            <option value="">{{ __('farms.form.barangay_choose') }}</option>
                            @foreach (\App\Support\LianBarangays::all() as $barangay)
                                <option value="{{ $barangay }}" @selected(old('barangay', $farm->barangay) === $barangay)>{{ \App\Services\Risk\HeatMapPanel::displayName($barangay) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fad-field">
                        <label class="fad-m-field-label" for="location_description">{{ __('farms.form.description') }}</label>
                        <input class="fad-input" id="location_description" name="location_description" type="text" maxlength="160" value="{{ old('location_description', $farm->location_description) }}" placeholder="{{ __('farms.form.description_hint') }}">
                    </div>
                </div>
            </fieldset>

            <div class="fad-field">
                <label class="fad-label" for="farm_type">{{ __('farms.form.type') }}</label>
                <select class="fad-input" id="farm_type" name="farm_type">
                    <option value="">{{ __('farms.form.type_choose') }}</option>
                    @foreach (['Irrigated', 'Rainfed'] as $type)
                        <option value="{{ $type }}" @selected(old('farm_type', $farm->farm_type) === $type)>{{ __('farms.form.types.'.$type) }}</option>
                    @endforeach
                </select>
            </div>

            @if ($associations !== null)
                {{-- The farm's farmers' association: required on Add Farm and asked again when the barangay changes. Only the
                     associations of the farm's barangay are listed (of its farm type first); FarmAssociations. --}}
                @php
                    $assocBarangay = old('barangay', $farm->barangay);
                    $assocOptions = \App\Services\Farms\FarmAssociations::optionsFor($assocBarangay, old('farm_type', $farm->farm_type), $associations);
                    $assocStatus = old('association_status', $farm->associationState() === 'not_declared' ? null : $farm->association_status);
                    $assocPick = (string) old('farmer_organization_id', $farm->farmer_organization_id);
                @endphp
                <fieldset class="ffm-group" data-association data-original-barangay="{{ $editing ? $farm->barangay : '' }}" @error('association_status') aria-invalid="true" @enderror>
                    <legend class="fad-label">{{ __('farms.association.question') }}</legend>
                    <p class="fad-m-small" data-association-hint @if ($assocBarangay) hidden @endif>{{ __('farms.association.choose_barangay') }}</p>
                    <p class="fad-m-small" data-association-again hidden>{{ __('farms.association.ask_again') }}</p>
                    <div class="ffm-choices">
                        <label class="ffm-choice" data-association-member @if ($assocOptions->isEmpty()) hidden @endif>
                            <input type="radio" name="association_status" value="member" @checked($assocStatus === 'member') data-association-answer>
                            <span>{{ __('farms.association.answers.member') }}</span>
                        </label>
                        <label class="ffm-choice">
                            <input type="radio" name="association_status" value="pending_other" @checked($assocStatus === 'pending_other') data-association-answer>
                            <span>{{ __('farms.association.answers.pending_other') }}</span>
                        </label>
                        <label class="ffm-choice">
                            <input type="radio" name="association_status" value="not_member" @checked($assocStatus === 'not_member') data-association-answer>
                            <span>{{ __('farms.association.answers.not_member') }}</span>
                        </label>
                    </div>

                    <div class="fad-field ffm-size" data-association-panel="member">
                        <label class="fad-m-field-label" for="farmer_organization_id">{{ __('farms.association.pick') }}</label>
                        <select class="fad-input" id="farmer_organization_id" name="farmer_organization_id" data-association-pick @error('farmer_organization_id') aria-invalid="true" @enderror>
                            <option value="">{{ __('farms.association.pick_choose') }}</option>
                            @foreach ($assocOptions as $option)
                                <option value="{{ $option['id'] }}" @selected($assocPick === (string) $option['id'])>{{ $option['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="fad-field ffm-size" data-association-panel="pending_other">
                        <label class="fad-m-field-label" for="association_other_name">{{ __('farms.association.other_name') }}</label>
                        <input class="fad-input" id="association_other_name" name="association_other_name" type="text" maxlength="120" value="{{ old('association_other_name', $farm->association_other_name) }}" @error('association_other_name') aria-invalid="true" @enderror>
                        <span class="fad-m-small">{{ __('farms.association.other_hint') }}</span>
                    </div>
                    <script type="application/json" data-association-list>@json($associations->values())</script>
                </fieldset>
            @endif

            <fieldset class="ffm-group" data-size-methods>
                <legend class="fad-label">{{ __('farms.form.size_question') }}</legend>
                <div class="ffm-choices">
                    @foreach (\App\Models\Farm::SIZE_METHODS as $m)
                        <label class="ffm-choice">
                            <input type="radio" name="farm_size_method" value="{{ $m }}" @checked($sizeMethod === $m) data-size-method>
                            <span>{{ __('farms.form.methods.'.$m) }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="ffm-size" data-size-panel="exact">
                    <div class="ffm-grid">
                        <div class="fad-field">
                            <label class="fad-m-field-label" for="farm_size_value">{{ __('farms.form.value') }}</label>
                            <input class="fad-input" id="farm_size_value" name="farm_size_value" type="number" inputmode="decimal" min="0" step="any" value="{{ $value }}" @error('farm_size_value') aria-invalid="true" @enderror>
                        </div>
                        <div class="fad-field">
                            <label class="fad-m-field-label" for="farm_size_unit">{{ __('farms.form.unit') }}</label>
                            <select class="fad-input" id="farm_size_unit" name="farm_size_unit">
                                @foreach (['hectares', 'square_meters'] as $u)
                                    <option value="{{ $u }}" @selected($unit === $u)>{{ __('farms.form.units.'.$u) }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <span class="fad-m-small">{{ __('farms.form.sqm_hint') }}</span>
                </div>

                <div class="ffm-size" data-size-panel="estimated">
                    <label class="fad-m-field-label" for="farm_size_estimate_range">{{ __('farms.form.estimate') }}</label>
                    <select class="fad-input" id="farm_size_estimate_range" name="farm_size_estimate_range" @error('farm_size_estimate_range') aria-invalid="true" @enderror>
                        <option value=""></option>
                        @foreach (array_keys(\App\Models\Farm::ESTIMATE_RANGES) as $range)
                            <option value="{{ $range }}" @selected(old('farm_size_estimate_range', $farm->farm_size_estimate_range) === $range)>{{ __('farms.size.ranges.'.$range) }}</option>
                        @endforeach
                    </select>
                </div>

                <p class="fad-m-small ffm-size" data-size-panel="mapped">{{ __('farms.form.mapped_hint') }}</p>
                <p class="fad-m-small ffm-size" data-size-panel="unknown">{{ __('farms.form.unknown_hint') }}</p>
            </fieldset>

            <div class="ffm-actions">
                <button type="submit" class="fad-btn">{{ __('farms.form.save') }}</button>
                <a class="fad-btn is-quiet" href="{{ route('farmer.my-farm') }}">{{ __('farms.form.cancel') }}</a>
            </div>
        </form>
    </div>

    <script>
        // Only the size fields of the chosen way are shown (all of them stay usable without scripts).
        document.addEventListener('DOMContentLoaded', () => {
            const group = document.querySelector('[data-size-methods]');
            if (!group) return;
            const show = () => {
                const chosen = group.querySelector('[data-size-method]:checked')?.value || 'unknown';
                group.querySelectorAll('[data-size-panel]').forEach((panel) => { panel.hidden = panel.dataset.sizePanel !== chosen; });
            };
            group.addEventListener('change', show);
            show();
        });

        // The association question: only the associations of the chosen barangay (of the farm type first, as on the
        // server), the field for the chosen answer, and the question asked again when the barangay changes.
        document.addEventListener('DOMContentLoaded', () => {
            const box = document.querySelector('[data-association]');
            if (!box) return;
            const form = box.closest('form');
            const barangay = form.querySelector('#barangay');
            const type = form.querySelector('#farm_type');
            const pick = box.querySelector('[data-association-pick]');
            const memberChoice = box.querySelector('[data-association-member]');
            let list = [];
            try { list = JSON.parse(box.querySelector('[data-association-list]').textContent) || []; } catch (e) { list = []; }
            const placeholder = pick.options[0].cloneNode(true);

            const panels = () => {
                const chosen = box.querySelector('[data-association-answer]:checked')?.value || '';
                box.querySelectorAll('[data-association-panel]').forEach((panel) => { panel.hidden = panel.dataset.associationPanel !== chosen; });
            };
            const options = () => {
                const here = list.filter((o) => o.barangay === barangay.value);
                const typed = here.filter((o) => !o.farm_type || o.farm_type === type.value);
                const shown = type.value && typed.length ? typed : here;
                const keep = pick.value;
                pick.replaceChildren(placeholder.cloneNode(true), ...shown.map((o) => new Option(o.name, String(o.id), false, String(o.id) === keep)));
                memberChoice.hidden = shown.length === 0;
                box.querySelector('[data-association-hint]').hidden = barangay.value !== '';
                if (memberChoice.hidden) memberChoice.querySelector('input').checked = false;
                panels();
            };

            barangay.addEventListener('change', () => {
                const moved = box.dataset.originalBarangay !== '' && barangay.value !== box.dataset.originalBarangay;
                box.querySelector('[data-association-again]').hidden = !moved;
                if (moved || box.dataset.originalBarangay === '') box.querySelectorAll('[data-association-answer]').forEach((r) => { r.checked = false; });
                pick.value = '';
                options();
            });
            type.addEventListener('change', options);
            box.addEventListener('change', panels);
            panels();
        });
    </script>
</x-app-layout>
