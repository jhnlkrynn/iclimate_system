<x-app-layout>
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">Account Center</div>
            <h1 class="h2 fw-bold mb-2">Profile</h1>
            <p class="mb-0" style="color: var(--ic-ink-mid);">Manage your account information, password, and account access.</p>
        </div>
    </section>

    <div class="row g-4">
        <div class="col-xl-6">
            <div class="card no-lift h-100">
                <div class="card-body p-4">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>
        </div>

        <div class="col-xl-6">
            <div class="card no-lift h-100">
                <div class="card-body p-4">
                    @include('profile.partials.update-password-form')
                </div>
            </div>
        </div>

        @if (auth()->user()->role === \App\Models\User::ROLE_FARMER)
            <div class="col-12">
                <div class="card no-lift">
                    <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <div>
                            <h2 class="h4 fw-bold mb-2">Farm Location and Land Size</h2>
                            <p class="mb-0 text-muted">Set your land size and draw the exact perimeter of your farm on the map.</p>
                        </div>
                        <a class="btn btn-primary" href="{{ route('farmer.boundary.edit') }}">Set Farm Location &amp; Size</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-12">
            <div class="card no-lift border-danger-subtle">
                <div class="card-body p-4">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
