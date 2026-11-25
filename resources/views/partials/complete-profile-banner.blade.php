@props(['dismissible' => true])
@php
    $bannerUser = auth()->user();
    $missingLabels = $bannerUser ? \App\Support\FarmerProfileCompletion::missingLabels($bannerUser) : [];
    $hasFarms = $bannerUser && \App\Support\ActiveFarm::available() && \App\Support\ActiveFarm::farms($bannerUser)->isNotEmpty();
@endphp
@if ($missingLabels !== [])
<div class="mb-4 rounded-xl border border-sage-200 bg-sage-50 px-4 py-3.5 text-ink flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" role="region" aria-label="{{ __('farmer.complete_profile.banner_title') }}" data-complete-profile-banner>
    <div class="min-w-0">
        <p class="font-semibold text-sm sm:text-base m-0 text-sage-900">{{ $hasFarms ? implode(' · ', $missingLabels) : __('farmer.complete_profile.banner_text') }}</p>
    </div>
    <div class="flex flex-wrap items-center gap-2 shrink-0">
        <a href="{{ route('farmer.complete-profile.show') }}" class="fad-btn is-compact">{{ __('farmer.complete_profile.banner_button') }}</a>
        @if ($dismissible)
            <form method="POST" action="{{ route('farmer.complete-profile.dismiss') }}" class="m-0">
                @csrf
                <button type="submit" class="fad-btn is-light is-compact" data-dismiss-complete-profile>{{ __('farmer.complete_profile.dismiss') }}</button>
            </form>
        @endif
    </div>
</div>
@endif
