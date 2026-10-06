<x-guest-layout>
    @if ($emailAvailable ?? true)
        <div class="mb-4 text-sm text-[#44604E]">
            {{ __('Enter your email address and we will email you a link to choose a new password.') }}
        </div>
    @else
        {{-- Email is not set up on this server: say so plainly instead of promising a link that never arrives. --}}
        <div class="mb-4 text-sm text-[#44604E]" role="status" data-reset-unavailable>
            Password reset by email is not available yet. Please ask the Municipal Agriculture Office or IT personnel to set a new password for your account.
        </div>
    @endif

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" data-loading="true">
        @csrf

        <!-- Email Address -->
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <x-primary-button data-loading-text="Sending link...">
                {{ __('Email Password Reset Link') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
