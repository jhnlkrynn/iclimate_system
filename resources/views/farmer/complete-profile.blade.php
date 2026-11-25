<x-app-layout>
    <x-slot:title>{{ __('farmer.complete_profile.page_title') }}</x-slot:title>

    @php($fil = app()->getLocale() === 'fil')

    <style @nonce>
        .cp-wrap { max-width: 920px; margin: 0 auto; display: flex; flex-direction: column; gap: 20px; }
        .cp-hero {
            padding: 24px 28px;
            border-radius: 18px;
            background: linear-gradient(135deg, var(--sage-100, #E6ECE4) 0%, var(--sage-50, #F3F6F2) 100%);
            border: 1px solid var(--sage-200, #CCD8C8);
            color: var(--sage-800, #364432);
        }
        .cp-kicker {
            display: inline-block;
            font-size: .78rem;
            font-weight: 700;
            letter-spacing: .04em;
            text-transform: uppercase;
            color: var(--sage-700, #485A42);
            margin-bottom: 6px;
        }
        .cp-hero h1 { margin: 0 0 6px; font-size: clamp(1.45rem, 2.5vw, 1.9rem); font-weight: 800; color: var(--sage-800, #364432); }
        .cp-hero p { margin: 0; font-size: .98rem; color: #4d5c50; max-width: 66ch; }

        .cp-card {
            background: #fff;
            border: 1px solid var(--sage-200, #CCD8C8);
            border-radius: 16px;
            padding: 22px 24px;
        }
        .cp-section-head { margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid var(--sage-100, #E6ECE4); }
        .cp-section-head h2 { margin: 0; font-size: 1.08rem; font-weight: 700; color: var(--sage-800, #364432); }
        .cp-section-head p { margin: 2px 0 0; font-size: .86rem; color: #5b6660; }

        .cp-label { display: block; font-size: .88rem; font-weight: 700; color: var(--sage-800, #364432); margin-bottom: 6px; }
        .cp-hint { margin: 6px 0 0; font-size: .8rem; color: #5b6660; }

        .cp-org-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
            gap: 8px;
            max-height: 230px;
            overflow-y: auto;
            padding: 4px 2px;
        }
        .cp-org-chip { position: relative; margin: 0; cursor: pointer; }
        .cp-org-chip input { position: absolute; opacity: 0; pointer-events: none; }
        .cp-org-chip span {
            display: flex;
            flex-direction: column;
            gap: 2px;
            min-height: 44px;
            padding: 9px 12px;
            border: 1px solid var(--sage-200, #CCD8C8);
            border-radius: 10px;
            background: #fff;
            color: var(--sage-800, #364432);
            font-size: .86rem;
            font-weight: 600;
            line-height: 1.3;
            transition: background-color .15s ease, border-color .15s ease, color .15s ease;
        }
        .cp-org-chip span small { font-size: .76rem; font-weight: 500; color: #5b6660; }
        .cp-org-chip:hover span { background: var(--sage-50, #F3F6F2); border-color: var(--sage-300, #A9BBA3); }
        .cp-org-chip input:checked + span {
            background: var(--sage-700, #485A42);
            border-color: var(--sage-700, #485A42);
            color: #fff;
        }
        .cp-org-chip input:checked + span small { color: rgba(255, 255, 255, .82); }
        .cp-org-chip input:focus-visible + span { outline: 3px solid var(--gov-gold, #d97706); outline-offset: 2px; }

        .cp-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            padding-top: 4px;
        }
        .cp-btn-primary {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 44px; padding: 10px 22px; border: 0; border-radius: 10px;
            background: var(--sage-700, #485A42); color: #fff; font-weight: 700; font-size: .95rem;
            cursor: pointer; text-decoration: none; transition: background-color .15s ease;
        }
        .cp-btn-primary:hover { background: var(--sage-800, #364432); color: #fff; }
        .cp-btn-secondary {
            display: inline-flex; align-items: center; justify-content: center;
            min-height: 44px; padding: 10px 20px; border: 1px solid var(--sage-200, #CCD8C8); border-radius: 10px;
            background: #fff; color: var(--sage-800, #364432); font-weight: 700; font-size: .95rem;
            text-decoration: none; transition: background-color .15s ease;
        }
        .cp-btn-secondary:hover { background: var(--sage-50, #F3F6F2); color: var(--sage-800, #364432); }

        @media (max-width: 640px) {
            .cp-hero { padding: 18px 18px; }
            .cp-card { padding: 18px 16px; }
            .cp-actions > * { width: 100%; }
        }
    </style>

    <div class="fad frec cp-wrap" data-complete-profile>
        <header class="cp-hero">
            <span class="cp-kicker">{{ __('farmer.nav.home') }}</span>
            <h1>{{ __('farmer.complete_profile.page_title') }}</h1>
            <p>{{ __('farmer.complete_profile.page_intro') }}</p>
        </header>

        <form method="POST" action="{{ route('farmer.complete-profile.store') }}" class="flex flex-col gap-4" data-complete-profile-form>
            @csrf

            <section class="cp-card">
                <div class="cp-section-head">
                    <h2>{{ $fil ? 'Iyong Profile at Samahan' : 'Your Profile & Association' }}</h2>
                    <p>{{ $fil ? 'Ilagay ang iyong barangay, numero ng telepono, at pangunahing samahan.' : 'Tell us your barangay, contact number, and primary farmers’ association.' }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="cp-barangay" class="cp-label">{{ __('farmer.complete_profile.barangay_label') }}</label>
                        <select id="cp-barangay" name="barangay" class="form-select w-full @error('barangay') is-invalid @enderror" required>
                            <option value="">{{ __('farmer.complete_profile.barangay_placeholder') }}</option>
                            @foreach ($barangays as $b)
                                <option value="{{ $b }}" @selected(old('barangay', $primaryFarm?->barangay ?? $user->registeredBarangay()) === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                        @error('barangay')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-contact" class="cp-label">{{ __('farmer.complete_profile.contact_label') }}</label>
                        <input id="cp-contact" type="tel" name="contact_number" value="{{ old('contact_number', $user->contact_number ?: $profile?->contact_number) }}" class="form-control w-full @error('contact_number') is-invalid @enderror" placeholder="09XX XXX XXXX" required>
                        @error('contact_number')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div class="md:col-span-2">
                        <label for="cp-association" class="cp-label">{{ __('farmer.complete_profile.association_label') }}</label>
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
                        <p class="cp-hint">{{ __('farmer.complete_profile.association_hint') }}</p>
                        @error('association_choice')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            @if ($organizations->count() > 1)
                @php($selectedOthers = array_map('strval', (array) old('other_organizations', $profile?->organizations?->pluck('id')->all() ?? [])))
                <section class="cp-card">
                    <div class="cp-section-head">
                        <h2>{{ __('farmer.complete_profile.other_orgs_label') }}</h2>
                        <p>{{ $fil ? 'Pindutin ang mga samahang kinabibilangan mo bukod sa pangunahing samahan.' : 'Tap any additional organizations you belong to.' }}</p>
                    </div>
                    <div class="cp-org-grid" role="group" aria-label="{{ __('farmer.complete_profile.other_orgs_label') }}" data-cp-other-orgs>
                        @foreach ($organizations as $org)
                            <label class="cp-org-chip" data-barangay="{{ $org->barangay }}">
                                <input type="checkbox" name="other_organizations[]" value="{{ $org->id }}" @checked(in_array((string) $org->id, $selectedOthers, true))>
                                <span>
                                    {{ $org->name }}
                                    <small>{{ $org->barangay }}</small>
                                </span>
                            </label>
                        @endforeach
                    </div>
                    <p class="cp-hint" data-cp-other-empty hidden>{{ $fil ? 'Walang ibang samahan na nakalista sa napiling barangay.' : 'No organizations listed for the selected barangay.' }}</p>
                    @error('other_organizations')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                </section>
            @endif

            <section class="cp-card">
                <div class="cp-section-head">
                    <h2>{{ $fil ? 'Iyong Unang Bukid' : 'Your First Farm' }}</h2>
                    <p>{{ $fil ? 'Gagamitin ang sukat at uri ng bukid para sa tantiya ng ani.' : 'Farm size and water source help us calculate your seasonal yield estimate.' }}</p>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label for="cp-farm-name" class="cp-label">{{ __('farmer.complete_profile.farm_name_label') }}</label>
                        <input id="cp-farm-name" type="text" name="farm_name" value="{{ old('farm_name', $primaryFarm?->farm_name ?? 'Farm 1') }}" class="form-control w-full @error('farm_name') is-invalid @enderror" required>
                        @error('farm_name')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-farm-type" class="cp-label">{{ __('farmer.complete_profile.farm_type_label') }}</label>
                        <select id="cp-farm-type" name="farm_type" class="form-select w-full @error('farm_type') is-invalid @enderror" required>
                            @foreach ($farmTypes as $type)
                                <option value="{{ $type }}" @selected(old('farm_type', $primaryFarm?->farm_type ?? $profile?->farm_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('farm_type')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>

                    <div>
                        <label for="cp-farm-size" class="cp-label">{{ __('farmer.complete_profile.farm_size_label') }}</label>
                        <input id="cp-farm-size" type="number" step="0.01" min="0.01" name="farm_size_hectares" value="{{ old('farm_size_hectares', $primaryFarm?->farm_size_hectares ?? $profile?->farm_area) }}" class="form-control w-full @error('farm_size_hectares') is-invalid @enderror" placeholder="1.50" required>
                        @error('farm_size_hectares')<p class="text-sm text-red-700 mt-1">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>

            <div class="cp-actions">
                <button type="submit" class="cp-btn-primary">{{ __('farmer.complete_profile.submit') }}</button>
                <a href="{{ route('farmer.dashboard') }}" class="cp-btn-secondary">{{ __('farmer.complete_profile.later') }}</a>
            </div>
        </form>
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

                const otherGrid = document.querySelector('[data-cp-other-orgs]');
                const otherEmpty = document.querySelector('[data-cp-other-empty]');
                if (otherGrid) {
                    let visible = 0;
                    otherGrid.querySelectorAll('.cp-org-chip').forEach((chip) => {
                        const match = Boolean(val) && chip.dataset.barangay === val;
                        chip.hidden = ! match;
                        const input = chip.querySelector('input[type="checkbox"]');
                        if (! match && reset && input) {
                            input.checked = false;
                        }
                        if (match) visible += 1;
                    });
                    otherGrid.hidden = visible === 0;
                    if (otherEmpty) otherEmpty.hidden = visible > 0;
                }
            };
            brgy.addEventListener('change', () => sync(true));
            sync(false);
        });
    </script>
</x-app-layout>
