<x-guest-layout>
    <div class="mb-4 text-sm text-sage-700">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 font-semibold text-sm text-sage-700">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between">
        <form method="POST" action="{{ route('verification.send') }}" data-loading="true">
            @csrf

            <div>
                <x-primary-button data-loading-text="Sending...">
                    {{ __('Resend Verification Email') }}
                </x-primary-button>
            </div>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <button type="submit" class="underline text-sm text-sage-700 hover:text-sage-800 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-sage-500/40">
                {{ __('Log Out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
