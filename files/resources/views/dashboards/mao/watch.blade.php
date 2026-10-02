@php $watch = $mao['watch']; @endphp
@if ($watch['state'] === 'watch')
    <section class="mtd-card" aria-labelledby="mtdWatchTitle" data-mao-watch>
        <div class="mtd-card-head">
            <h2 id="mtdWatchTitle">Barangays to Watch</h2>
            <a class="mtd-btn is-quiet" href="#mtdRiskMap">View Risk Map</a>
        </div>
        <ul class="mtd-watch">
            @foreach ($watch['items'] as $item)
                <li>
                    <div class="mtd-watch-main">
                        <strong>{{ $item['barangay'] }}</strong>
                        <span class="mtd-badge {{ $item['level'] === 'Severe' ? 'is-sev' : 'is-high' }}"><span aria-hidden="true">{{ $item['level'] === 'Severe' ? '‼' : '!' }}</span> {{ strtoupper($item['level']) }}</span>
                    </div>
                    @if ($item['reason'])<p>{{ $item['reason'] }}</p>@endif
                </li>
            @endforeach
        </ul>
    </section>
@elseif ($watch['state'] === 'clear')
    <section class="mtd-card mtd-clear" aria-label="Barangays to watch" data-mao-watch>
        <div class="mtd-clear-body">
            <p class="mtd-allgood"><span aria-hidden="true">✓</span> No high-risk barangays detected</p>
            <small>All monitored barangays are currently below the high-risk threshold.</small>
        </div>
        <a class="mtd-btn is-quiet" href="#mtdRiskMap">View Risk Map</a>
    </section>
@endif
