<x-app-layout>
    <x-slot:title>Change your password</x-slot:title>
    {{-- Panel revision 4.4 A (FirstPasswordController): an account on the default password chooses its own first. --}}
    <div class="mfd itd itp itp-form" style="max-width: 640px;">
        <x-it.page-header kicker="Account security" title="Change your password"
            lede="You signed in with the default password. Choose your own password so that only you can use this account." />

        <section class="mfd-card" style="padding: 20px;" data-first-password>
            <p class="mb-3"><strong>{{ auth()->user()->name }}</strong> · {{ auth()->user()->positionLabel() }}<br>
                <span class="text-muted">{{ auth()->user()->email }}</span></p>
            <form method="POST" action="{{ route('password.first-change.update') }}" class="pr-form" data-loading="true">
                @csrf
                <label class="form-label" for="newPassword">New password</label>
                <input class="form-control" id="newPassword" name="password" type="password" autocomplete="new-password" required>
                <p class="small text-muted mb-1">At least 8 characters, with upper and lower case letters and a number.</p>
                @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                <label class="form-label" for="newPasswordConfirm">Type it again</label>
                <input class="form-control" id="newPasswordConfirm" name="password_confirmation" type="password" autocomplete="new-password" required>
                <button class="btn btn-primary mt-3" type="submit" data-loading-text="Saving...">Save new password</button>
            </form>
            @if ($canSkip)
                <form method="POST" action="{{ route('password.first-change.skip') }}" class="mt-3">
                    @csrf
                    <button class="btn btn-outline-secondary" type="submit" data-skip-password>Skip for now</button>
                    <p class="small text-muted mt-1 mb-0">For testing and the thesis defense only. You will be asked again at your next sign-in.</p>
                </form>
            @endif
        </section>
    </div>
</x-app-layout>
