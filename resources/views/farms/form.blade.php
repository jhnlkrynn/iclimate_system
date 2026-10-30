<x-app-layout>
    {{-- Add Farm / Edit Farm (FarmController): name, farm type, barangay and sitio, the farmers' association question and
         the farm's exact size (hectares or square meters). Full width like the green header (up to ~1200px): two columns
         when the content area has room, one on a phone. Same look as the other farmer pages. --}}
    @php
        $editing = $farm->exists;
        // The size field starts from what is known: an exact size as entered, the map's measured area, or the middle of
        // an older estimate (Farm::modelArea()); empty when the size is not known.
        $unit = old('farm_size_unit', $farm->farm_size_method === \App\Models\Farm::SIZE_EXACT && $farm->farm_size_unit === \App\Models\Farm::UNIT_SQUARE_METERS ? 'square_meters' : 'hectares');
        $known = $editing ? $farm->modelArea() : null;
        $value = old('farm_size_value', $known === null ? ''
            : ($unit === 'square_meters' ? rtrim(rtrim(number_format($known * 10000, 2, '.', ''), '0'), '.') : rtrim(rtrim(number_format($known, 4, '.', ''), '0'), '.')));
        $maxHectares = (float) config('farms.max_hectares', 10000);
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
                <div class="frec-note is-danger ffm-wide" role="alert">
                    @foreach ($errors->all() as $message)<p class="mb-0">{{ $message }}</p>@endforeach
                </div>
            @endif

            <div class="fad-field" data-ffm-cell="name">
                <label class="fad-label" for="farm_name">{{ __('farms.form.name') }}</label>
                <input class="fad-input" id="farm_name" name="farm_name" type="text" maxlength="80" value="{{ old('farm_name', $editing ? $farm->farm_name : '') }}" placeholder="{{ $defaultName }}" aria-describedby="farmNameHint">
                <span class="fad-m-small" id="farmNameHint">{{ __('farms.form.name_hint', ['default' => $defaultName]) }}</span>
            </div>

            <div class="fad-field" data-ffm-cell="type">
                <label class="fad-label" for="farm_type">{{ __('farms.form.type') }}</label>
                <select class="fad-input" id="farm_type" name="farm_type">
                    <option value="">{{ __('farms.form.type_choose') }}</option>
                    @foreach (['Irrigated', 'Rainfed'] as $type)
                        <option value="{{ $type }}" @selected(old('farm_type', $farm->farm_type) === $type)>{{ __('farms.form.types.'.$type) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fad-field" data-ffm-cell="barangay">
                <label class="fad-label" for="barangay">{{ __('farms.form.barangay') }}</label>
                <select class="fad-input" id="barangay" name="barangay" required @error('barangay') aria-invalid="true" @enderror>
                    <option value="">{{ __('farms.form.barangay_choose') }}</option>
                    @foreach (\App\Support\LianBarangays::all() as $barangay)
                        <option value="{{ $barangay }}" @selected(old('barangay', $farm->barangay) === $barangay)>{{ \App\Services\Risk\HeatMapPanel::displayName($barangay) }}</option>
                    @endforeach
                </select>
            </div>

            <div class="fad-field" data-ffm-cell="sitio">
                <label class="fad-label" for="location_description">{{ __('farms.form.description') }}</label>
                <input class="fad-input" id="location_description" name="location_description" type="text" maxlength="160" value="{{ old('location_description', $farm->location_description) }}" placeholder="{{ __('farms.form.description_hint') }}">
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
                <fieldset class="ffm-group ffm-wide ffm-assoc" data-association data-original-barangay="{{ $editing ? $farm->barangay : '' }}" @error('association_status') aria-invalid="true" @enderror>
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

            {{-- The farm's exact size: a number above 0 (at most config farms.max_hectares) in hectares or square meters
                 (square meters are turned into hectares). Always saved as an exact size. --}}
            <div class="fad-field" data-ffm-cell="size">
                <label class="fad-label" for="farm_size_value">{{ __('farms.form.value') }}</label>
                <input class="fad-input" id="farm_size_value" name="farm_size_value" type="number" inputmode="decimal" min="{{ $unit === 'square_meters' ? 1 : 0.0001 }}" max="{{ $unit === 'square_meters' ? $maxHectares * 10000 : $maxHectares }}" step="any" required value="{{ $value }}" aria-describedby="farmSizeHint" data-max-hectares="{{ $maxHectares }}" @error('farm_size_value') aria-invalid="true" @enderror>
                <span class="fad-m-small" id="farmSizeHint">{{ __('farms.form.size_hint') }}</span>
            </div>
            <div class="fad-field" data-ffm-cell="unit">
                <label class="fad-label" for="farm_size_unit">{{ __('farms.form.unit') }}</label>
                <select class="fad-input" id="farm_size_unit" name="farm_size_unit" required>
                    @foreach (['hectares', 'square_meters'] as $u)
                        <option value="{{ $u }}" @selected($unit === $u)>{{ __('farms.form.units.'.$u) }}</option>
                    @endforeach
                </select>
                <span class="fad-m-small">{{ __('farms.form.sqm_hint') }}</span>
            </div>

            <div class="ffm-actions ffm-wide">
                <a class="fad-btn is-quiet" href="{{ route('farmer.my-farm') }}">{{ __('farms.form.cancel') }}</a>
                <button type="submit" class="fad-btn">{{ __('farms.form.save') }}</button>
            </div>
        </form>
    </div>

    <script>
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

        // Keep the browser limit in the selected unit; the server always validates the converted hectares too.
        document.addEventListener('DOMContentLoaded', () => {
            const value = document.querySelector('#farm_size_value');
            const unit = document.querySelector('#farm_size_unit');
            if (!value || !unit) return;
            const syncLimits = () => {
                const sqm = unit.value === 'square_meters';
                value.min = sqm ? '1' : '0.0001';
                value.max = String(Number(value.dataset.maxHectares) * (sqm ? 10000 : 1));
            };
            unit.addEventListener('change', syncLimits);
            syncLimits();
        });
    </script>
</x-app-layout>
