@php
    // No reading for this barangay (null from the weather service) is an empty record, never null: every field
    // below is read once, with an explicit "missing" value, so the template never indexes into nothing.
    $weather = is_array($barangayWeather ?? null) ? $barangayWeather : [];
    $farmerView = $barangayAdvisory ? new \App\Support\FarmerAdvisoryView($barangayAdvisory) : null;
    $badge = $farmerView?->statusBadge();
    $value = static function ($number, string $unit = ''): ?string {
        return is_numeric($number) ? rtrim(rtrim(number_format((float) $number, 1, '.', ''), '0'), '.').$unit : null;
    };
    $time = static fn ($value) => !empty($value) ? \Illuminate\Support\Carbon::parse($value)->timezone('Asia/Manila') : null;

    $status = $weather['status'] ?? null;
    $condition = $weather['condition'] ?? null;
    $temperature = $weather['temperature_c'] ?? null;
    $rainfall24h = $weather['rainfall_24h_mm'] ?? null;
    $rainChance = $weather['rain_probability_percent'] ?? $weather['precip_probability_percent'] ?? null;
    $humidity = $weather['humidity_percent'] ?? null;
    $wind = $weather['wind_speed_kmh'] ?? null;
    // The provider's own reading time; when iClimate last checked is shown separately.
    $observed = $time($weather['observed_at'] ?? null);
    $checked = $time($weather['fetched_at'] ?? null);
    $stale = (bool) ($weather['stale'] ?? false);

    // Shown as current conditions only with a real reading; otherwise the card says the data is unavailable.
    $available = $weather !== [] && $status !== 'unavailable' && is_numeric($temperature);
    $chips = $available ? array_filter([
        'temperature' => $value($temperature, '°C'),
        'rain' => ($v = $value($rainfall24h, ' mm')) ? 'Rainfall '.$v : null,
        'weather' => ($v = $value($rainChance, '%')) ? $v.' rain chance' : null,
        'humidity' => ($v = $value($humidity, '%')) ? $v.' humidity' : null,
        'wind' => ($v = $value($wind, ' km/h')) ? 'Wind '.$v : null,
    ]) : [];
@endphp

{{-- iClimate's own advice from this barangay's weather: labelled as local, never as an official PAGASA advisory. --}}
<article class="fad-card fad-feature" data-barangay-advisory data-barangay="{{ $selectedBarangay }}">
    <div class="fad-feature-head">
        <div>
            <span class="fad-kicker">Local Farming Advisory</span>
            <h3 class="fad-title"><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'map'])</span>Barangay {{ $selectedBarangay }}, Lian</h3>
        </div>
        <div class="fad-acard-tags">
            @if($badge)<span class="fad-badge is-{{ $badge['tone'] }}">@include('dashboards.mao.icon', ['name' => $badge['icon']])<span>{{ $badge['label'] }}</span></span>@endif
            @if($available)<span class="fad-badge {{ $stale ? 'is-watch' : 'is-good' }}">@include('dashboards.mao.icon', ['name' => 'clock'])<span>{{ $stale ? 'Update delayed' : 'Latest weather' }}</span></span>@endif
        </div>
    </div>

    @if($available)
        <p class="fad-label">Current conditions{{ !empty($condition) ? ' · '.$condition : '' }}</p>
        <ul class="fad-chips" aria-label="Current conditions in {{ $selectedBarangay }}">
            @foreach($chips as $icon => $text)
                <li><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span>{{ $text }}</li>
            @endforeach
        </ul>
        @if($stale)
            <p class="fad-support">Weather update is delayed. These are the last available readings{{ $observed ? ' from '.$observed->format('M j, g:i A') : '' }}, not current conditions.</p>
        @endif

        <div class="fad-feature-body">
            <div>
                <h4 class="fad-label">What is happening</h4>
                <p class="fad-body">{{ $farmerView?->farmerEffect() ?? ($barangayAdvisory?->title ?? 'Advice for '.$selectedBarangay.' will appear after the next weather update.') }}</p>
            </div>
            <div>
                <h4 class="fad-label">What farmers can do</h4>
                <p class="fad-body">{{ $farmerView?->farmerAction() ?? 'Check current field conditions and the latest official advisories before farm work.' }}</p>
            </div>
        </div>

        <div class="fad-feature-foot">
            <p class="fad-meta">
                <span class="fad-source">Based on: current weather + forecast data for {{ $selectedBarangay }} · Source: Open-Meteo + iClimate Local Guidance</span>
                <span><span class="fad-ico" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'clock'])</span>Updated: {{ $observed?->format('M j, Y · g:i A') ?? 'Not provided' }}@if($checked) · Checked: {{ $checked->format('g:i A') }}@endif</span>
                @if($barangayAdvisory?->valid_until)<span>Valid until: {{ $barangayAdvisory->valid_until->copy()->timezone('Asia/Manila')->format('M j, Y · g:i A') }}</span>@endif
            </p>
            @if($barangayAdvisory)
                <a class="fad-btn is-primary fad-view" href="{{ route('planting-advisories.show', $barangayAdvisory) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="View details: {{ $barangayAdvisory->title }}">View Details</a>
            @endif
        </div>
    @else
        <div class="fad-empty" role="status">
            <p class="fad-lead">Latest data temporarily unavailable</p>
            <p class="fad-body">Current weather for {{ $selectedBarangay }} could not be loaded. Lian-wide advice and official PAGASA advisories are below.</p>
        </div>
    @endif
</article>
