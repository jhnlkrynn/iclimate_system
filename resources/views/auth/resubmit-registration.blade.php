<x-guest-layout>
    <h1 class="text-xl font-semibold mb-3">Resubmit a Farmer application</h1>
    <p class="mb-4">Use your existing account details and explain the corrections for MAO Staff to review.</p>
    <form method="POST" action="{{ route('registration.resubmit') }}">
        @csrf
        <label for="resubmitEmail">Registered email</label>
        <input id="resubmitEmail" name="email" type="email" value="{{ old('email') }}" required class="block w-full mb-3">
        <label for="resubmitPassword">Password</label>
        <input id="resubmitPassword" name="password" type="password" required class="block w-full mb-3" autocomplete="current-password">
        <label for="resubmitNote">Corrections or explanation</label>
        <textarea id="resubmitNote" name="resubmission_note" required minlength="10" maxlength="2000" class="block w-full mb-3">{{ old('resubmission_note') }}</textarea>
        @if ($requiresCaptcha)
            <label for="resubmitCaptcha">Security code</label>
            <img src="{{ route('captcha.image', ['context' => 'login']) }}" alt="Security code image" width="190" height="64">
            <input id="resubmitCaptcha" name="captcha_answer" required maxlength="20" autocomplete="off" class="block w-full mb-3">
        @endif
        <x-input-error :messages="$errors->all()" class="mb-3" />
        <button type="submit" class="btn btn-primary">Submit for review</button>
    </form>
</x-guest-layout>
