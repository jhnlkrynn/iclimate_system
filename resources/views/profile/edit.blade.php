<x-app-layout>
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">Account Center</div>
            <h1 class="h2 fw-bold mb-2">Profile</h1>
            <p class="mb-0 text-white-50">Manage your account information, password, and account access.</p>
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

        <div class="col-12">
            <div class="card no-lift border-danger-subtle">
                <div class="card-body p-4">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>