{{-- The farmer dashboard's pop-ups. A button opens the one it names (data-mfd-popup): "Add planting date" the planting
     one, "Add farm size" the farm size one, "Update profile" the profile one, "Map my farm" the map one. They sit here,
     outside the refreshed sections, and start from what the signed-in farmer has saved. --}}
@php
    $pUser = auth()->user();
    $pProfile = $pUser->farmerProfile;
    $pToday = \App\Support\BusinessClock::today();
    $pPlan = \App\Http\Controllers\FarmerPlantingScheduleController::currentSchedule($pUser);
    $pArea = (float) ($pProfile?->farm_area ?? 0) > 0 ? number_format((float) $pProfile->farm_area, 2, '.', '') : '';
    $pBarangay = $pProfile?->barangay ?? $pUser->barangay;
    $pBoundary = [];
    try {
        $pBoundary = $pProfile?->farmBoundary?->boundary_coordinates ?? [];
    } catch (\Throwable) {
        // an unreadable boundary opens as an empty map
    }
    $req = '<span class="mfd-pm-req" aria-hidden="true">*</span><span class="mfd-pm-sr">('.e(__('farmer.popup.required')).')</span>';
@endphp

{{-- Add Planting Information: planting date + farm size --}}
<x-farmer-popup name="planting" icon="calendar" :title="__('farmer.planting_info.title')" :intro="__('farmer.planting_info.intro')"
    :action="route('farmer.dashboard.planting-info')" :save="__('farmer.planting_info.save')" :saving="__('farmer.planting_info.saving')"
    data-text-date-required="{{ __('farmer.planting_info.date_required') }}" data-text-date-invalid="{{ __('farmer.planting_info.date_invalid') }}"
    data-text-area-invalid="{{ __('farmer.planting_info.area_invalid') }}" data-text-area-decimals="{{ __('farmer.planting_info.area_decimals') }}"
    data-text-selected="{{ __('farmer.planting_info.date_selected') }}">
    <div class="fad-field">
        <label class="mfd-pm-label" for="mfdPlantingDate">{{ __('farmer.planting_info.date_label') }} {!! $req !!}</label>
        <input class="fad-input" type="date" id="mfdPlantingDate" name="planting_date" required
            min="{{ $pToday->copy()->subYear()->toDateString() }}" max="{{ $pToday->copy()->addYear()->toDateString() }}"
            value="{{ $pPlan?->planting_date?->toDateString() }}" aria-describedby="mfdPlantingDateShown mfdPlantingDateError">
        <p class="mfd-pm-help" id="mfdPlantingDateShown" data-mfd-planting-shown hidden></p>
        <p class="mfd-pm-error" id="mfdPlantingDateError" data-mfd-error="planting_date" hidden></p>
    </div>
    <div class="fad-field">
        <label class="mfd-pm-label" for="mfdFarmArea">{{ __('farmer.planting_info.area_label') }} {!! $req !!}</label>
        <div class="mfd-pm-unit">
            <input class="fad-input" type="number" id="mfdFarmArea" name="farm_area" required inputmode="decimal" min="0.01" max="10000" step="0.01" placeholder="1.50"
                value="{{ $pArea }}" aria-describedby="mfdFarmAreaUnit mfdFarmAreaHelp mfdFarmAreaError">
            <span id="mfdFarmAreaUnit">{{ __('farmer.planting_info.area_unit') }}</span>
        </div>
        <p class="mfd-pm-help" id="mfdFarmAreaHelp">{{ __('farmer.planting_info.area_help') }}</p>
        <p class="mfd-pm-error" id="mfdFarmAreaError" data-mfd-error="farm_area" hidden></p>
    </div>
</x-farmer-popup>

{{-- Add Farm Size: the farm size only --}}
<x-farmer-popup name="farm-size" icon="yield" :title="__('farmer.popup.size.title')" :intro="__('farmer.popup.size.intro')"
    :action="route('farmer.dashboard.farm-size')" :save="$pArea !== '' ? __('farmer.popup.size.update') : __('farmer.popup.size.save')" :saving="__('farmer.popup.size.saving')"
    data-text-add="{{ __('farmer.popup.size.save') }}" data-text-update="{{ __('farmer.popup.size.update') }}" data-text-save-info="{{ __('farmer.popup.size.save_info') }}" data-text-invalid="{{ __('farmer.popup.size.invalid') }}" data-text-decimals="{{ __('farmer.popup.size.decimals') }}">
    <div class="fad-field">
        <label class="mfd-pm-label" for="mfdSizeArea">{{ __('farmer.popup.size.label') }} {!! $req !!}</label>
        <div class="mfd-pm-unit">
            <input class="fad-input" type="number" id="mfdSizeArea" name="farm_area" required inputmode="decimal" min="0.01" max="10000" step="0.01" placeholder="1.50"
                value="{{ $pArea }}" aria-describedby="mfdSizeAreaUnit mfdSizeAreaHelp mfdSizeAreaError">
            <span id="mfdSizeAreaUnit">{{ __('farmer.popup.size.unit') }}</span>
        </div>
        <p class="mfd-pm-help" id="mfdSizeAreaHelp">{{ __('farmer.popup.size.help') }}</p>
        <p class="mfd-pm-error" id="mfdSizeAreaError" data-mfd-error="farm_area" hidden></p>
    </div>
    {{-- Only when opened as "Farm Information" from Quick Actions (see .mfd-pm-info in farmer-dashboard.css). --}}
    <div class="fad-field mfd-pm-info">
        <label class="mfd-pm-label" for="mfdSizeFarmType">{{ __('farmer.popup.size.farm_type') }}</label>
        <select class="fad-input" id="mfdSizeFarmType" name="farm_type" aria-describedby="mfdSizeFarmTypeError">
            <option value="">{{ __('farmer.popup.profile.farm_type_choose') }}</option>
            @foreach (\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)
                <option value="{{ $farmType }}" @selected($pProfile?->farm_type === $farmType)>{{ __('farmer.popup.profile.farm_type_'.strtolower($farmType)) }}</option>
            @endforeach
        </select>
        <p class="mfd-pm-error" id="mfdSizeFarmTypeError" data-mfd-error="farm_type" hidden></p>
    </div>
</x-farmer-popup>

{{-- Update Profile: the account details the Profile page keeps, plus the farm type the dashboard asks for --}}
<x-farmer-popup name="profile" size="md" icon="farmers" :title="__('farmer.popup.profile.title')" :intro="__('farmer.popup.profile.intro')"
    :action="route('farmer.dashboard.profile')" :save="__('farmer.popup.profile.save')" :saving="__('farmer.popup.profile.saving')"
    data-text-name-required="{{ __('farmer.popup.profile.name_required') }}" data-text-email-invalid="{{ __('farmer.popup.profile.email_invalid') }}"
    data-text-contact-invalid="{{ __('farmer.popup.profile.contact_invalid') }}">
    <fieldset class="mfd-pm-group">
        <legend>{{ __('farmer.popup.profile.personal') }}</legend>
        <div class="mfd-pm-grid">
            <div class="fad-field is-full">
                <label class="mfd-pm-label" for="mfdProfileName">{{ __('farmer.popup.profile.name') }} {!! $req !!}</label>
                <input class="fad-input" type="text" id="mfdProfileName" name="name" required maxlength="255" autocomplete="name" value="{{ $pUser->name }}" aria-describedby="mfdProfileNameError">
                <p class="mfd-pm-error" id="mfdProfileNameError" data-mfd-error="name" hidden></p>
            </div>
            <div class="fad-field">
                <label class="mfd-pm-label" for="mfdProfileContact">{{ __('farmer.popup.profile.contact') }}</label>
                <input class="fad-input" type="tel" id="mfdProfileContact" name="contact_number" maxlength="20" inputmode="tel" autocomplete="tel" value="{{ $pUser->contact_number }}" aria-describedby="mfdProfileContactHelp mfdProfileContactError">
                <p class="mfd-pm-help" id="mfdProfileContactHelp">{{ __('farmer.popup.profile.contact_help') }}</p>
                <p class="mfd-pm-error" id="mfdProfileContactError" data-mfd-error="contact_number" hidden></p>
            </div>
            <div class="fad-field">
                <label class="mfd-pm-label" for="mfdProfileEmail">{{ __('farmer.popup.profile.email') }} {!! $req !!}</label>
                <input class="fad-input" type="email" id="mfdProfileEmail" name="email" required maxlength="255" autocomplete="email" value="{{ $pUser->email }}" aria-describedby="mfdProfileEmailError">
                <p class="mfd-pm-error" id="mfdProfileEmailError" data-mfd-error="email" hidden></p>
            </div>
        </div>
    </fieldset>
    <fieldset class="mfd-pm-group">
        <legend>{{ __('farmer.popup.profile.farm') }}</legend>
        <div class="mfd-pm-grid">
            <div class="fad-field is-full">
                <label class="mfd-pm-label" for="mfdProfileAddress">{{ __('farmer.popup.profile.address') }}</label>
                <input class="fad-input" type="text" id="mfdProfileAddress" name="address" maxlength="500" autocomplete="street-address" value="{{ $pUser->address }}" aria-describedby="mfdProfileAddressError">
                <p class="mfd-pm-error" id="mfdProfileAddressError" data-mfd-error="address" hidden></p>
            </div>
            <div class="fad-field">
                <label class="mfd-pm-label" for="mfdProfileBarangay">{{ __('farmer.popup.profile.barangay') }}</label>
                <select class="fad-input" id="mfdProfileBarangay" name="barangay" aria-describedby="mfdProfileBarangayError">
                    <option value="">{{ __('farmer.popup.profile.barangay_choose') }}</option>
                    @foreach (\App\Support\LianBarangays::all() as $barangayName)
                        <option value="{{ $barangayName }}" @selected($pBarangay === $barangayName)>{{ $barangayName }}</option>
                    @endforeach
                </select>
                <p class="mfd-pm-error" id="mfdProfileBarangayError" data-mfd-error="barangay" hidden></p>
            </div>
            <div class="fad-field">
                <label class="mfd-pm-label" for="mfdProfileFarmType">{{ __('farmer.popup.profile.farm_type') }}</label>
                <select class="fad-input" id="mfdProfileFarmType" name="farm_type" aria-describedby="mfdProfileFarmTypeError">
                    <option value="">{{ __('farmer.popup.profile.farm_type_choose') }}</option>
                    @foreach (\App\Models\FarmerPlantingSchedule::FARM_TYPES as $farmType)
                        <option value="{{ $farmType }}" @selected($pProfile?->farm_type === $farmType)>{{ __('farmer.popup.profile.farm_type_'.strtolower($farmType)) }}</option>
                    @endforeach
                </select>
                <p class="mfd-pm-error" id="mfdProfileFarmTypeError" data-mfd-error="farm_type" hidden></p>
            </div>
        </div>
    </fieldset>
</x-farmer-popup>

{{-- Map My Farm: the farm's outline, drawn the same way as on the My Farm Map page --}}
<x-farmer-popup name="farm-map" size="lg" icon="map" :title="__('farmer.popup.map.title')" :intro="__('farmer.popup.map.intro')"
    :action="route('farmer.dashboard.farm-map')" :save="__('farmer.popup.map.save')" :saving="__('farmer.popup.map.saving')"
    :data-boundary="json_encode($pBoundary)" data-text-required="{{ __('farmer.popup.map.required') }}" data-text-unavailable="{{ __('farmer.popup.map.unavailable') }}"
    data-text-area-none="{{ __('farmer.popup.map.area_none') }}"
    data-lian-url="{{ asset('geojson/lian-boundary.geojson') }}" data-barangays-url="{{ asset('geojson/lian-barangays.geojson') }}"
    data-text-none="{{ __('farmer.popup.map.none') }}" data-text-lian="{{ __('farmer.popup.map.lian') }}" data-text-your-farm="{{ __('farmer.popup.map.your_farm') }}"
    data-text-status-ok="{{ __('farmer.popup.map.status_ok') }}" data-text-status-invalid="{{ __('farmer.popup.map.status_invalid') }}"
    data-text-barangay-outside="{{ __('farmer.popup.map.barangay_outside') }}" data-text-barangay-unknown="{{ __('farmer.popup.map.barangay_unknown') }}"
    data-text-note-ok="{{ __('farmer.popup.map.note_ok') }}" data-text-note-more="{{ __('farmer.popup.map.note_more') }}"
    data-text-outside-title="{{ __('farmer.popup.map.outside_title') }}" data-text-outside="{{ __('farmer.popup.map.outside') }}"
    data-text-partly="{{ __('farmer.popup.map.partly') }}" data-text-unknown="{{ __('farmer.popup.map.unknown') }}">
    <div class="fad-field">
        <p class="mfd-pm-label" id="mfdFarmMapLabel">{{ __('farmer.popup.map.label') }} {!! $req !!}</p>
        <p class="mfd-pm-instruction"><strong>{{ __('farmer.popup.map.instruction') }}</strong> {{ __('farmer.popup.map.how') }}</p>
        <div class="mfd-pm-map" id="mfdFarmMap" role="application" aria-label="{{ __('farmer.popup.map.map_label') }}" aria-describedby="mfdFarmMapError mfdFarmMapPlace" data-mfd-field="boundary_coordinates"></div>
        <p class="mfd-pm-error" id="mfdFarmMapError" data-mfd-error="boundary_coordinates" hidden></p>
        {{-- Where the marked farm is, read from the official Lian and barangay boundaries as the farmer marks it. --}}
        <div class="mfd-pm-place" id="mfdFarmMapPlace" data-mfd-map-place data-state="none" role="status" aria-live="polite">
            <p class="mfd-pm-place-title">{{ __('farmer.popup.map.place_title') }}</p>
            <dl>
                <div><dt>{{ __('farmer.popup.map.municipality') }}</dt><dd>{{ __('farmer.popup.map.lian') }}</dd></div>
                <div><dt>{{ __('farmer.popup.map.barangay') }}</dt><dd data-mfd-map-barangay>{{ __('farmer.popup.map.none') }}</dd></div>
                <div><dt>{{ __('farmer.popup.map.status') }}</dt><dd data-mfd-map-status>{{ __('farmer.popup.map.none') }}</dd></div>
            </dl>
            <p class="mfd-pm-place-note" data-mfd-map-note hidden></p>
        </div>
        <div class="mfd-pm-maptools">
            <dl class="mfd-pm-mapstats">
                <div><dt>{{ __('farmer.popup.map.points') }}</dt><dd data-mfd-map-points>0</dd></div>
                <div><dt>{{ __('farmer.popup.map.area') }}</dt><dd data-mfd-map-area>{{ __('farmer.popup.map.area_none') }}</dd></div>
            </dl>
            <div class="mfd-pm-mapbtns">
                <button type="button" class="fad-btn is-quiet is-compact" data-mfd-map-undo>{{ __('farmer.popup.map.undo') }}</button>
                <button type="button" class="fad-btn is-quiet is-compact" data-mfd-map-clear>{{ __('farmer.popup.map.clear') }}</button>
            </div>
        </div>
        <p class="mfd-pm-help">{{ __('farmer.popup.map.privacy') }}</p>
    </div>
</x-farmer-popup>
