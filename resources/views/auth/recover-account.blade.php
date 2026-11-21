<x-guest-layout>
    {{-- Recovery for an account on a shared password (App\Services\Accounts\AccountRecovery). --}}
    <h1 class="text-lg font-semibold mb-2">Choose a new password</h1>
    @if (session('recovery') === 'email' && session('recovery_email_sent'))
        <p class="mb-4 text-sm" data-recovery="email">This account requires password recovery before signing in. We sent a link to your email. Open it to choose a new password, or use Option B below.</p>
    @elseif (session('recovery') === 'email')
        <p class="mb-4 text-sm" data-recovery="email-unavailable">This account requires password recovery before signing in. Email delivery is not configured on this server yet, so please use Option B (MAO Recovery Code) below.</p>
    @elseif (session('recovery') === 'mao')
        <p class="mb-4 text-sm" data-recovery="mao">This account requires password recovery before signing in. Use either recovery option below.</p>
    @else
        <p class="mb-4 text-sm">Enter a code from the Municipal Agriculture Office, or request a password-reset link by email.</p>
    @endif

    <div class="mb-5 rounded border border-gray-200 p-3 text-sm">
        <p class="font-semibold mb-1">Option A: Email Recovery</p>
        <p class="mb-2 text-gray-600">Request or resend a 60-minute reset link to your registered email address.</p>
        <form method="POST" action="{{ route('password.email') }}" class="flex gap-2 items-center">
            @csrf
            <input type="hidden" name="email" value="{{ old('email', session('recovery_email')) }}" />
            <x-secondary-button type="submit" :disabled="blank(old('email', session('recovery_email')))">Send / resend reset link</x-secondary-button>
        </form>
    </div>

    <div class="mb-2 text-sm">
        <p class="font-semibold mb-1">Option B: MAO Recovery Code</p>
        <p class="mb-3 text-gray-600">Visit or call the Municipal Agriculture Office. After they verify your identity, they give you a one-time code to enter below.</p>
    </div>

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
