<section>
    <header>
        <h2 class="text-lg font-semibold text-[#1B2B23]">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-[#44604E]">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-[#1B2B23]">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-[#2D6A4F] hover:text-[#1A3A2A] rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#52B788]/40">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-semibold text-sm text-[#2D6A4F]">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        @if ($user->role === \App\Models\User::ROLE_FARMER)
            <div>
                <x-input-label for="barangay" :value="__('Registered barangay')" />
                <select id="barangay" name="barangay" class="mt-1 block w-full rounded-md border-gray-300" autocomplete="address-level3">
                    <option value="">Select your exact barangay</option>
                    @foreach (\App\Support\LianBarangays::options(false) as $barangay => $label)
                        <option value="{{ $barangay }}" @selected(old('barangay', $user->registeredBarangay()) === $barangay)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-sm text-[#44604E]">Choose the exact barangay on your registration. Do not choose a general Poblacion label.</p>
                <x-input-error class="mt-2" :messages="$errors->get('barangay')" />
            </div>

            {{-- Farm size and type: used for the expected yield on Home and shown on My Farm. --}}
            <div>
                <x-input-label for="farm_area" :value="__('Farm size (hectares)')" />
                <x-text-input id="farm_area" name="farm_area" type="number" min="0.01" max="10000" step="0.01" class="mt-1 block w-full" :value="old('farm_area', $user->farmerProfile?->farm_area)" />
                <x-input-error class="mt-2" :messages="$errors->get('farm_area')" />
            </div>
            <div>
                <x-input-label for="farm_type" :value="__('Farm type')" />
                <select id="farm_type" name="farm_type" class="mt-1 block w-full rounded-md border-gray-300">
                    <option value="">Select</option>
                    @foreach (\App\Models\FarmerPlantingSchedule::FARM_TYPES as $type)
                        <option value="{{ $type }}" @selected(old('farm_type', $user->farmerProfile?->farm_type) === $type)>{{ __('farmer.my_farm.water.'.strtolower($type)) }}</option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('farm_type')" />
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-[#44604E]"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
