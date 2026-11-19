<x-guest-layout>
    {{-- Recovery for an account on a shared password (App\Services\Accounts\AccountRecovery). --}}
    <h1 class="text-lg font-semibold mb-2">Choose a new password</h1>
    @if (session('recovery') === 'email')
        <p class="mb-4 text-sm" data-recovery="email">Your password is used by other accounts too, so it is not safe. We sent a link to your email. Open it to choose a new password. You can also ask the Municipal Agriculture Office for a code.</p>
    @elseif (session('recovery') === 'mao')
        <p class="mb-4 text-sm" data-recovery="mao">Your password is used by other accounts too, so it is not safe. Visit or call the Municipal Agriculture Office. After they check that it is you, they give you a code. Enter it below.</p>
    @else
        <p class="mb-4 text-sm">Enter the code from the Municipal Agriculture Office (or from your email link).</p>
    @endif

    <form method="POST" action="{{ route('password.store') }}" data-loading="true" data-recovery-form>
        @csrf
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', session('recovery_email'))" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="token" value="Code" />
            <x-text-input id="token" class="block mt-1 w-full" type="text" name="token" required autocomplete="one-time-code" placeholder="ABCDE-FGHJK" />
            <x-input-error :messages="$errors->get('token')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password" :value="__('New password')" />
            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm new password')" />
            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>
        <div class="flex items-center justify-end mt-4">
            <x-primary-button>Save new password</x-primary-button>
        </div>
    </form>
</x-guest-layout>
