@php $f = $it['farmers']; @endphp
<section class="itd-card" aria-labelledby="itdFarmersTitle" data-it-farmers>
    <div class="itd-card-head">
        <h2 id="itdFarmersTitle">Farmer Database</h2>
        <a class="itd-btn is-quiet" href="{{ route('farmer-profiles.index') }}">View Farmer Analytics</a>
    </div>
    @if ($f['state'] === 'error')
        <p class="itd-error" role="alert">{{ $f['message'] }}</p>
    @elseif ($f['state'] === 'empty')
        <p class="itd-empty">No farmer profiles available.</p>
    @else
        <div class="itd-stats">
            <div><span class="itd-stat-label">Total Farmers</span><strong>{{ number_format($f['total_farmers']) }}</strong></div>
            <div><span class="itd-stat-label">Farmer Organizations</span><strong>{{ number_format($f['organizations']) }}</strong></div>
            <div><span class="itd-stat-label">Without Organization</span><strong>{{ number_format($f['without_organization']) }}</strong></div>
            <div><span class="itd-stat-label">Registered Farm Area</span><strong>{{ number_format($f['farm_area'], 2) }} <small>ha</small></strong></div>
            @if ($f['average_area'] !== null)
                <div><span class="itd-stat-label">Average Farm Size</span><strong>{{ number_format($f['average_area'], 2) }} <small>ha</small></strong></div>
            @endif
        </div>
        <p class="itd-foot">
            @if ($f['average_area'] !== null)
                Average farm size is taken over the {{ number_format($f['farms_with_area']) }} farmers with a recorded area.
            @endif
            @if ($f['note']) {{ $f['note'] }} @endif
        </p>
    @endif
</section>
