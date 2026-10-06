@php
    $account = auth()->user();
    $isStaffAccount = $account->role !== \App\Models\User::ROLE_FARMER;
@endphp

<x-app-layout>
    @if ($isStaffAccount)
        @php
            $initials = collect(explode(' ', trim($account->name)))->filter()->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))->take(2)->implode('');
            $isActive = $account->status === \App\Models\User::STATUS_ACTIVE;
        @endphp
        {{-- Staff accounts (IT and MAO): the IT dashboard design system (.mfd / .itd parts, IT page parts in it-pages.css).
             The three forms are the same partials every role uses. --}}
        <div class="mfd itd itp itp-form">
            <x-it.page-header kicker="Account Center" title="Account" lede="Manage your account information, password, and account access." />

            <section class="itp-section" aria-labelledby="accountSummaryTitle">
                <div class="itp-section-head"><h2 id="accountSummaryTitle">Account Summary</h2></div>
                <div class="mfd-card itp-account">
                    <div class="itp-avatar" aria-hidden="true">{{ $initials ?: '?' }}</div>
                    <div class="itp-account-id">
                        <span class="itp-account-name">{{ $account->name }}</span>
                        <span class="itp-account-email">{{ $account->email }}</span>
                        <div class="itp-account-pills">
                            <span class="mfd-pill is-info">@include('dashboards.mao.icon', ['name' => 'farmers'])<span>{{ $account->roleLabel() }}</span></span>
                            <span class="mfd-pill {{ $isActive ? 'is-good' : 'is-unk' }}">@include('dashboards.mao.icon', ['name' => $isActive ? 'check' : 'info'])<span>{{ $account->status }}</span></span>
                        </div>
                    </div>
                    <dl class="mfd-facts itd-rows itp-account-facts">
                        <div><dt>Member since</dt><dd>{{ $account->created_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Last active</dt><dd>{{ $account->last_active_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'Not recorded' }}</dd></div>
                        <div><dt>Email verified</dt><dd>{{ $account->email_verified_at?->timezone('Asia/Manila')->format('M j, Y') ?? 'Not verified' }}</dd></div>
                    </dl>
                </div>
            </section>

            <div class="itp-two">
                <div class="mfd-card">@include('profile.partials.update-profile-information-form')</div>
                <div class="mfd-card">@include('profile.partials.update-password-form')</div>
            </div>

            <div class="mfd-card is-danger">@include('profile.partials.delete-user-form')</div>
        </div>
    @else
    <section class="page-hero">
        <div>
            <div class="eyebrow mb-2">Account Center</div>
            <h1 class="h2 fw-bold mb-2">Profile</h1>
            <p class="mb-0" style="color: var(--ic-ink-mid);">Manage your account information, password, and account access.</p>
        </div>
    </section>

    <div class="row g-4">
        @if ($farmSetup)
            {{-- What is still missing from the farm information (moved here from the dashboard). --}}
            <div class="col-12" data-farm-setup>
                <div class="card no-lift">
                    <div class="card-body p-4">
                        @php $setupLocale = \App\Support\FarmerLanguage::resolve(request()); @endphp
                        <h2 class="h4 fw-bold mb-2">{{ __('farmer.profile_setup.title', [], $setupLocale) }}</h2>
                        @if ($farmSetup['items'])
                            <p class="text-muted mb-3">{{ __('farmer.setup.progress', ['done' => $farmSetup['done'], 'total' => $farmSetup['total']], $setupLocale) }}</p>
                            <ul class="list-unstyled d-grid gap-2 mb-0">
                                @foreach ($farmSetup['items'] as $item)
                                    <li><a class="fw-semibold" href="{{ $item['href'] }}">{{ $item['title'] }}</a> <span class="text-muted">{{ $item['benefit'] }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="mb-0 text-muted">{{ __('farmer.profile_setup.done', [], $setupLocale) }}</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

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
    @endif
</x-app-layout>
