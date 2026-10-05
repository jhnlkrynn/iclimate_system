@php
    $weather = is_array($barangayWeather ?? null) ? $barangayWeather : null;
    $farmerView = $barangayAdvisory ? new \App\Support\FarmerAdvisoryView($barangayAdvisory) : null;
    $value = static function ($number, string $unit = ''): string {
        if (! is_numeric($number)) {
            return 'Not available';
        }

        return rtrim(rtrim(number_format((float) $number, 2, '.', ''), '0'), '.').$unit;
    };
    $updated = !empty($weather['fetched_at']) ? \Illuminate\Support\Carbon::parse($weather['fetched_at'])->timezone('Asia/Manila') : null;
    $nextRefresh = !empty($weather['next_refresh_at']) ? \Illuminate\Support\Carbon::parse($weather['next_refresh_at'])->timezone('Asia/Manila') : null;
@endphp

<article class="fad-card" data-barangay-advisory data-barangay="{{ $selectedBarangay }}">
    <span class="fad-kicker">iClimate Barangay Advisory</span>
    <h3 class="fad-title">{{ $selectedBarangay }}, Lian</h3>

    @if($weather && ($weather['status'] ?? null) !== 'unavailable')
        <div class="fad-acard-tags">
            @if($farmerView)<span class="fad-badge is-{{ $farmerView->farmerRiskTone() }}">{{ $farmerView->farmerRiskLevel() }}</span>@endif
            <span class="fad-badge {{ ($weather['stale'] ?? false) ? 'is-watch' : 'is-good' }}">{{ ($weather['stale'] ?? false) ? 'Update delayed' : 'Latest weather update' }}</span>
        </div>
        <h4 class="fad-lead">{{ $barangayAdvisory?->title ?? ($weather['condition'] ?? 'Current Conditions') }}</h4>
        @if($weather['stale'] ?? false)
            <p class="fad-body">Weather update is temporarily delayed. These are the last available conditions for {{ $selectedBarangay }}.</p>
        @endif

        <h4 class="fad-label">Current Conditions</h4>
        <dl class="fad-acard-facts">
            <div><dt>Condition</dt><dd>{{ $weather['condition'] ?? 'Not available' }}</dd></div>
            <div><dt>Temperature</dt><dd>{{ $value($weather['temperature_c'] ?? null, '°C') }}</dd></div>
            <div><dt>Humidity</dt><dd>{{ $value($weather['humidity_percent'] ?? null, '%') }}</dd></div>
            <div><dt>Current precipitation</dt><dd>{{ $value($weather['rainfall_now_mm'] ?? null, ' mm') }}</dd></div>
            <div><dt>Wind</dt><dd>{{ $value($weather['wind_speed_kmh'] ?? null, ' km/h') }}</dd></div>
        </dl>

        <h4 class="fad-label">Expected Next 24 Hours</h4>
        <dl class="fad-acard-facts">
            <div><dt>Rain probability</dt><dd>{{ $value($weather['rain_probability_percent'] ?? $weather['precip_probability_percent'] ?? null, '%') }}</dd></div>
            <div><dt>Expected rainfall</dt><dd>{{ $value($weather['rainfall_24h_mm'] ?? null, ' mm') }}</dd></div>
        </dl>

        <h4 class="fad-label">What to do</h4>
        <p class="fad-body">{{ $barangayAdvisory?->recommended_action ?: 'Check current field conditions and the latest official advisories before farm work.' }}</p>
        <p class="fad-meta">
            <span class="fad-source">Source: Open-Meteo + iClimate Local Guidance</span>
            <span>Coordinates: {{ $weather['latitude'] }}, {{ $weather['longitude'] }}</span>
            <span>{{ ($weather['stale'] ?? false) ? 'Last available' : 'Updated' }}: {{ $updated?->format('M j, Y · g:i A') ?? 'Not available' }}</span>
            @if(!($weather['stale'] ?? false) && $nextRefresh)<span>Next refresh: {{ $nextRefresh->format('M j, Y · g:i A') }}</span>@endif
            @if($barangayAdvisory?->valid_until)<span>Valid until: {{ $barangayAdvisory->valid_until->copy()->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</span>@endif
        </p>
        @if($barangayAdvisory)
            <a class="fad-btn is-primary fad-view" href="{{ route('planting-advisories.show', $barangayAdvisory) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog">View Details</a>
        @endif
    @else
        <div class="fad-empty" role="status">
            <p class="fad-lead">Latest weather for {{ $selectedBarangay }} is temporarily unavailable.</p>
            <p class="fad-body">Official PAGASA updates and Lian-wide guidance remain available below.</p>
        </div>
    @endif
</article>
