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
                    $assocOptions = \App\Services\Farms\FarmAssociations::registeredForBarangay($assocBarangay, $associations);
                    $assocStatus = old('association_status', $farm->associationState() === 'not_declared' ? null : $farm->association_status);
                    $assocPick = (string) old('farmer_organization_id', $farm->farmer_organization_id);
                    $associationRequired = ! $editing || $farm->associationState() === 'not_declared';
                @endphp
                <fieldset class="ffm-group ffm-wide ffm-assoc" data-association data-original-barangay="{{ $editing ? $farm->barangay : '' }}" data-association-required="{{ $associationRequired ? 'true' : 'false' }}" data-label-not-member="{{ __('farms.association.dropdown_not_member') }}" @error('association_status') aria-invalid="true" @enderror>
                    <legend class="fad-label">{{ __('farms.association.question') }}</legend>
                    <p class="fad-m-small" data-association-hint @if ($assocBarangay) hidden @endif>{{ __('farms.association.choose_barangay') }}</p>
                    <p class="fad-m-small" data-association-again hidden>{{ __('farms.association.ask_again') }}</p>
                    <div class="fad-field ffm-association-picker">
                        <label class="fad-m-field-label" for="farmer_organization_id">{{ __('farms.association.pick') }}</label>
                        <input type="hidden" name="association_status" value="{{ $assocStatus ?? '' }}" data-association-status>
                        <select class="fad-input" id="farmer_organization_id" name="farmer_organization_id" data-association-select aria-describedby="associationHint" aria-required="{{ $associationRequired ? 'true' : 'false' }}" @disabled(! $assocBarangay) @error('farmer_organization_id') aria-invalid="true" @enderror>
                            <option value="" data-association-status="">{{ __('farms.association.dropdown_choose') }}</option>
                            @foreach ($assocOptions as $option)
                                <option value="{{ $option['id'] }}" data-association-status="member" @selected($assocStatus === 'member' && $assocPick === (string) $option['id'])>{{ $option['name'] }}</option>
                            @endforeach
                            <option value="" data-association-status="not_member" @selected($assocStatus === 'not_member')>{{ __('farms.association.dropdown_not_member') }}</option>
                        </select>
                        <span class="fad-m-small" id="associationHint">{{ __('farms.association.choose_barangay') }}</span>
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

    <script @nonce>
        // The association question: only the associations of the chosen barangay (of the farm type first, as on the
        // server), the field for the chosen answer, and the question asked again when the barangay changes.
        document.addEventListener('DOMContentLoaded', () => {
            const box = document.querySelector('[data-association]');
            if (!box) return;
            const form = box.closest('form');
            const barangay = form.querySelector('#barangay');
            const pick = box.querySelector('[data-association-select]');
            const status = box.querySelector('[data-association-status]');
            const farmType = form.querySelector('#farm_type');
            let list = [];
            try { list = JSON.parse(box.querySelector('[data-association-list]').textContent) || []; } catch (e) { list = []; }
            const placeholder = pick.options[0].cloneNode(true);

            const options = () => {
                const here = list.filter((o) => o.barangay === barangay.value);
                const shown = here;
                const keepStatus = status.value;
                const keepId = pick.value;
                pick.replaceChildren(placeholder.cloneNode(true), ...shown.map((o) => {
                    const option = new Option(o.name, String(o.id), false, keepStatus === 'member' && String(o.id) === keepId);
                    option.dataset.associationStatus = 'member';
                    return option;
                }));
                const notMember = new Option(box.dataset.labelNotMember, '', false, keepStatus === 'not_member');
                notMember.dataset.associationStatus = 'not_member';
                pick.append(notMember);
                pick.disabled = barangay.value === '';
                box.querySelector('[data-association-hint]').hidden = barangay.value !== '';
                if (keepStatus === 'member' && ![...pick.options].some((o) => o.dataset.associationStatus === 'member' && o.value === keepId)) {
                    status.value = '';
                    pick.value = '';
                }
            };

            pick.addEventListener('change', () => {
                status.value = pick.selectedOptions[0]?.dataset.associationStatus || '';
                if (farmType && farmType.value === '' && status.value === 'member') {
                    const selectedOrg = list.find((o) => String(o.id) === String(pick.value));
                    if (selectedOrg && (selectedOrg.farm_type === 'Irrigated' || selectedOrg.farm_type === 'Rainfed')) {
                        farmType.value = selectedOrg.farm_type;
                    }
                }
            });

            barangay.addEventListener('change', () => {
                const moved = box.dataset.originalBarangay !== '' && barangay.value !== box.dataset.originalBarangay;
                box.querySelector('[data-association-again]').hidden = !moved;
                box.dataset.associationRequired = 'true';
                pick.setAttribute('aria-required', 'true');
                status.value = '';
                pick.value = '';
                options();
            });
            if (status.value === 'member' || status.value === 'not_member') {
                options();
            } else {
                pick.disabled = barangay.value === '';
            }
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
