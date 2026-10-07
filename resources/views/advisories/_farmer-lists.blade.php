@php
    $hasResults = $barangayAdvisory || $myBarangayAdvisories->isNotEmpty() || $otherBarangayAdvisories->isNotEmpty() || $lianWideAdvisories->isNotEmpty()
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
@endif

{{-- In the order that matters to the farmer: 1. their barangay, 2. other Lian barangays, 3. all of Lian,
     4. wider PAGASA bulletins that include Lian. Nothing for other Batangas towns is shown. --}}
@unless($viewAllBarangays)
<section class="fad-list" aria-labelledby="myBarangayAdvisoriesTitle">
    <div class="fad-list-head">
        <div>
            <span class="fad-kicker">{{ $selectedBarangay && $selectedBarangay !== $farmerBarangay ? 'Barangay' : 'For your barangay' }}</span>
            <h2 id="myBarangayAdvisoriesTitle">{{ $selectedBarangay ? $selectedBarangay.', Lian, Batangas' : 'My Barangay' }}</h2>
            @unless($farmerBarangay)
                <p class="fad-support">Add your barangay to your profile to see advice for your barangay first.</p>
            @endunless
        </div>
        @unless($farmerBarangay)
            <a class="fad-btn is-quiet" href="{{ route('profile.edit') }}">@include('dashboards.mao.icon', ['name' => 'farmers'])<span>Update Profile</span></a>
        @endunless
    </div>
    @if($selectedBarangay)
        <ul class="fad-cards">
            <li class="fad-cards-item">@include('advisories._barangay-weather-card', compact('selectedBarangay', 'barangayAdvisory', 'barangayWeather'))</li>
            @foreach($myBarangayAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @endif
</section>
@endunless

@if($otherBarangayAdvisories->isNotEmpty() || $viewAllBarangays)
<section class="fad-list" aria-labelledby="otherBarangayAdvisoriesTitle">
    <div class="fad-list-head"><div>
        <h2 id="otherBarangayAdvisoriesTitle">{{ $viewAllBarangays ? 'Lian Barangays' : 'Other Lian Barangays' }}</h2>
        <p class="fad-support">{{ $viewAllBarangays ? 'Advice for each barangay of Lian.' : 'Barangays of Lian where weather may affect farm work.' }}</p>
    </div></div>
    @if($otherBarangayAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($otherBarangayAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @else
        <p class="fad-support">No barangay advice is active right now.</p>
    @endif
</section>
@endif

<section class="fad-list" aria-labelledby="lianWideAdvisoriesTitle">
    <div class="fad-list-head"><div><span class="fad-kicker">All of Lian</span><h2 id="lianWideAdvisoriesTitle">Lian, Batangas</h2><p class="fad-support">iClimate farming guidance for the whole municipality, calculated from the latest forecast.</p></div></div>
    @php
        $forecast = $lianForecast ?? null;
        $n = fn ($v, $unit) => $v === null ? null : rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.').$unit;
        $forecastTime = $forecast['fetched_at'] ?? null ? $forecast['fetched_at']->copy()->timezone('Asia/Manila') : null;
    @endphp
    {{-- Today's Lian forecast: the values the Lian-wide rules are calculated from. An outdated forecast is never shown as current. --}}
    @if($forecast && $forecast['freshness'] !== 'outdated')
        @php
            $tiles = array_filter([
                ['rain', $n($forecast['rainfall_mm'], ' mm'), 'Rainfall'],
                ['weather', $n($forecast['probability'], '%'), 'Rain chance'],
                ['temperature', $forecast['temperature_max'] !== null ? $n($forecast['temperature_max'], '°C') : null, 'Highest temp.'],
                ['humidity', $n($forecast['humidity'], '%'), 'Humidity'],
                ['wind', $n($forecast['wind_speed'], ' km/h'), 'Wind'],
            ], fn ($tile) => $tile[1] !== null);
        @endphp
        <div class="fad-strip" aria-label="Today's forecast for Lian">
            <p class="fad-strip-head"><span class="fad-label">Today in Lian</span><span class="fad-strip-time"><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'clock'])</span>Open-Meteo forecast · Updated {{ $forecastTime?->format('M j, g:i A') }}{{ $forecast['freshness'] === 'delayed' ? ' (update delayed)' : '' }}</span></p>
            <ul class="fad-tiles">
                @foreach($tiles as [$icon, $value, $label])
                    <li><span class="fad-tile-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span><span><strong>{{ $value }}</strong><small>{{ $label }}</small></span></li>
                @endforeach
            </ul>
        </div>
    @else
        <p class="fad-support" role="status">Latest data temporarily unavailable: no current Lian forecast to calculate from.</p>
    @endif
    @if($lianWideAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($lianWideAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory, 'wide' => true])
            @endforeach
        </ul>
    @elseif($advisoryCheck['all_checked'] && $weatherAvailable)
        <div class="fad-empty"><p class="fad-lead">{{ $activeFilterCount ? 'No advisories match these filters' : 'No Lian-wide advice right now' }}</p><p class="fad-body">{{ $activeFilterCount ? 'Try clearing the filters to see available advice.' : 'New advice will appear here when the weather calls for it.' }}</p></div>
    @endif
</section>

<section class="fad-list" aria-labelledby="officialPagasaUpdatesTitle">
    <div class="fad-list-head"><div><h2 id="officialPagasaUpdatesTitle">Official PAGASA Advisories</h2><p class="fad-support">PAGASA bulletins for Batangas Province or CALABARZON that include Lian. They are shown as PAGASA issued them.</p></div></div>
    @if($officialPagasaAdvisories->isNotEmpty())
        <ul class="fad-cards">
            @foreach($officialPagasaAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory, 'relevantBarangay' => $selectedBarangay])
            @endforeach
        </ul>
    @elseif($advisoryCheck['pagasa_complete'])
        <p class="fad-support">No active PAGASA advisory currently includes Lian.</p>
    @endif
</section>

@if($additionalPagasaInformation->isNotEmpty())
    <section class="fad-list" aria-labelledby="additionalPagasaTitle">
        <div class="fad-list-head"><div><h2 id="additionalPagasaTitle">Other PAGASA Information</h2><p class="fad-support">National outlook — supporting information, not specific to Lian.</p></div></div>
        <ul class="fad-cards">
            @foreach($additionalPagasaInformation as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory, 'relevantBarangay' => $selectedBarangay, 'wide' => true])
            @endforeach
        </ul>
    </section>
@endif
