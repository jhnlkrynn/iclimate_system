<x-app-layout>
    <x-slot:title>{{ __('farmer.complete_profile.page_title') }}</x-slot:title>

    <div class="fad frec" data-complete-profile>
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farmer.nav.home') }}</span>
                <h1>{{ __('farmer.complete_profile.page_title') }}</h1>
                <p>{{ __('farmer.complete_profile.page_intro') }}</p>
            </div>
        </header>

        <section class="frec-panel">
            <form method="POST" action="{{ route('farmer.complete-profile.store') }}" class="flex flex-col gap-5" data-complete-profile-form>
                @csrf

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="cp-barangay" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.barangay_label') }}</label>
                        <select id="cp-barangay" name="barangay" class="form-select w-full @error('barangay') is-invalid @enderror" required>
                            <option value="">{{ __('farmer.complete_profile.barangay_placeholder') }}</option>
                            @foreach ($barangays as $b)
                                <option value="{{ $b }}" @selected(old('barangay', $primaryFarm?->barangay ?? $user->registeredBarangay()) === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                        @error('barangay')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-association" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.association_label') }}</label>
                        @php
                            $currentChoice = old('association_choice');
                            if ($currentChoice === null) {
                                $currentChoice = $primaryFarm?->farmer_organization_id
                                    ?? $profile?->farmer_organization_id
                                    ?? ($primaryFarm?->association_status === 'not_member' ? 'not_member' : '');
                            }
                        @endphp
                        <select id="cp-association" name="association_choice" class="form-select w-full @error('association_choice') is-invalid @enderror" required>
                            <option value="">{{ __('farms.association.dropdown_choose_short') }}</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}" data-barangay="{{ $org->barangay }}" @selected((string) $currentChoice === (string) $org->id)>
                                    {{ $org->name }} ({{ $org->barangay }})
                                </option>
                            @endforeach
                            <option value="not_member" @selected((string) $currentChoice === 'not_member')>{{ __('farms.association.dropdown_not_member') }}</option>
                        </select>
                        <p class="text-xs text-muted mt-1">{{ __('farmer.complete_profile.association_hint') }}</p>
                        @error('association_choice')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                @if ($organizations->count() > 1)
                    @php($selectedOthers = array_map('strval', (array) old('other_organizations', $profile?->organizations?->pluck('id')->all() ?? [])))
                    <div>
                        <label for="cp-other-orgs" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.other_orgs_label') }}</label>
                        <select id="cp-other-orgs" name="other_organizations[]" multiple size="4" class="form-select w-full">
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}" @selected(in_array((string) $org->id, $selectedOthers, true))>{{ $org->name }} · {{ $org->barangay }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-muted mt-1">{{ __('farmer.complete_profile.other_orgs_hint') }}</p>
                        @error('other_organizations')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                @endif

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="cp-contact" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.contact_label') }}</label>
                        <input id="cp-contact" type="tel" name="contact_number" value="{{ old('contact_number', $user->contact_number ?: $profile?->contact_number) }}" class="form-control w-full @error('contact_number') is-invalid @enderror" placeholder="09XX XXX XXXX" required>
                        @error('contact_number')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-farm-name" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.farm_name_label') }}</label>
                        <input id="cp-farm-name" type="text" name="farm_name" value="{{ old('farm_name', $primaryFarm?->farm_name ?? 'Farm 1') }}" class="form-control w-full @error('farm_name') is-invalid @enderror" required>
                        @error('farm_name')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="cp-farm-type" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.farm_type_label') }}</label>
                        <select id="cp-farm-type" name="farm_type" class="form-select w-full @error('farm_type') is-invalid @enderror" required>
                            @foreach ($farmTypes as $type)
                                <option value="{{ $type }}" @selected(old('farm_type', $primaryFarm?->farm_type ?? $profile?->farm_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('farm_type')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-farm-size" class="block text-sm font-semibold text-ink mb-1">{{ __('farmer.complete_profile.farm_size_label') }}</label>
                        <input id="cp-farm-size" type="number" step="0.01" min="0.01" name="farm_size_hectares" value="{{ old('farm_size_hectares', $primaryFarm?->farm_size_hectares ?? $profile?->farm_area) }}" class="form-control w-full @error('farm_size_hectares') is-invalid @enderror" placeholder="1.50" required>
                        @error('farm_size_hectares')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 pt-2">
                    <button type="submit" class="fad-btn">{{ __('farmer.complete_profile.submit') }}</button>
                    <a href="{{ route('farmer.dashboard') }}" class="fad-btn is-light">{{ __('farmer.complete_profile.later') }}</a>
                </div>
            </form>
        </section>
    </div>

    <script @nonce>
        document.addEventListener('DOMContentLoaded', () => {
            const brgy = document.getElementById('cp-barangay');
            const assoc = document.getElementById('cp-association');
            if (! brgy || ! assoc) return;

            const sync = (reset = false) => {
                const val = brgy.value;
                [...assoc.options].forEach((opt) => {
                    if (! opt.dataset.barangay) return;
                    const match = Boolean(val) && opt.dataset.barangay === val;
                    opt.hidden = ! match;
                    opt.disabled = ! match;
                });
                if (reset && assoc.selectedOptions[0]?.disabled) {
                    assoc.value = '';
                }
            };
            brgy.addEventListener('change', () => sync(true));
            sync(false);
        });
    </script>
</x-app-layout>
