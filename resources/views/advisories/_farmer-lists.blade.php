@php
    $hasResults = $barangayAdvisory || $myBarangayAdvisories->isNotEmpty() || $lianWideAdvisories->isNotEmpty()
        || $officialPagasaAdvisories->isNotEmpty() || $additionalPagasaInformation->isNotEmpty();
    $weatherAvailable = $advisoryCheck['weather_available'];
@endphp
@if($advisoryCheck['unavailable'] || $advisoryCheck['weather_unavailable'] || ($advisoryCheck['weather_checked'] && ! $weatherAvailable))
    <div class="fad-empty" role="status">
        <p class="fad-lead">Latest Advisories Temporarily Unavailable</p>
        <p class="fad-body">
            @if($advisoryCheck['unavailable'] && ! $weatherAvailable)
                Advisory information is temporarily unavailable. Please check official PAGASA and LGU announcements.
            @elseif($advisoryCheck['unavailable'])
                PAGASA advisories are temporarily unavailable. Showing the latest available local guidance.
            @else
                Current forecast data is temporarily unavailable. Saved active advisories are shown below.
            @endif
        </p>
        <p class="fad-support">Last successful PAGASA check: {{ $advisoryCheck['last_successful_check']?->copy()->timezone('Asia/Manila')->format('M j, Y · g:i A') ?? 'Not available' }}</p>
    </div>
@endif
@if(! $advisoryCheck['all_checked'] && ! $hasResults && ! $advisoryCheck['unavailable'] && ! $advisoryCheck['weather_checked'])
    <div class="fad-empty" role="status"><p class="fad-lead">Checking the latest advisories…</p><p class="fad-body">PAGASA and local forecast sources have not finished checking yet. Use Refresh Advisories to check again.</p></div>
@elseif($advisoryCheck['pagasa_complete'] && $pagasaAdvisoryCount === 0 && ! $activeFilterCount)
    <p class="fad-support">No active official advisories currently apply to Lian.</p>
@endif

<section class="fad-list" aria-labelledby="myBarangayAdvisoriesTitle">
    <div class="fad-list-head">
        <div>
            <h2 id="myBarangayAdvisoriesTitle">My Barangay</h2>
            @if($farmerBarangay)
                <p class="fad-support">{{ $farmerBarangay }}, Lian, Batangas</p>
            @else
                <p class="fad-support">Add your barangay to receive barangay-specific guidance. Lian-wide guidance and official PAGASA updates remain available below.</p>
                <a class="fad-btn is-quiet is-compact" href="{{ route('profile.edit') }}">Update Profile</a>
            @endif
            @if($selectedBarangay && $selectedBarangay !== $farmerBarangay)<p class="fad-support">Viewing: {{ $selectedBarangay }}, Lian</p>@endif
        </div>
    </div>
    @if($selectedBarangay)
        @include('advisories._barangay-weather-card', compact('selectedBarangay', 'barangayAdvisory', 'barangayWeather'))
    @endif
    @if($myBarangayAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($myBarangayAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @endif
</section>

<section class="fad-list" aria-labelledby="officialPagasaUpdatesTitle">
    <div class="fad-list-head"><div><h2 id="officialPagasaUpdatesTitle">Official PAGASA Updates</h2><p class="fad-support">Official bulletins that explicitly cover your barangay, Lian, Batangas Province, or CALABARZON. Their original geographic scope is preserved.</p></div></div>
    @if($officialPagasaAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($officialPagasaAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory, 'relevantBarangay' => $selectedBarangay])
            @endforeach
        </ul>
    @elseif($advisoryCheck['pagasa_complete'])
        <p class="fad-support">No active official PAGASA advisory currently applies to Lian.</p>
    @endif
</section>

<section class="fad-list" aria-labelledby="lianWideAdvisoriesTitle">
    <div class="fad-list-head"><div><h2 id="lianWideAdvisoriesTitle">Lian-wide Guidance</h2><p class="fad-support">Municipality-wide local farm guidance generated separately from official PAGASA bulletins.</p></div></div>
    @if($lianWideAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($lianWideAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @elseif($advisoryCheck['all_checked'] && $weatherAvailable)
        <div class="fad-empty"><p class="fad-lead">{{ $activeFilterCount ? 'No advisories match these filters' : 'No Active Local Guidance for Lian' }}</p><p class="fad-body">{{ $activeFilterCount ? 'Try clearing the filters to see available guidance.' : 'No municipality-wide iClimate guidance is currently active.' }}</p></div>
    @endif
</section>

@if($additionalPagasaInformation->isNotEmpty())
    <section class="fad-list" aria-labelledby="additionalPagasaTitle">
        <div class="fad-list-head"><div><h2 id="additionalPagasaTitle">Additional Regional / National Information</h2><p class="fad-support">Broader outlooks are supporting information, not barangay-specific advisories.</p></div></div>
        <ul class="fad-cards">
            @foreach($additionalPagasaInformation as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory, 'relevantBarangay' => $selectedBarangay])
            @endforeach
        </ul>
    </section>
@endif
