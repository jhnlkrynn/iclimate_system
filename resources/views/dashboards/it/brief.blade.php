{{-- The Overview's short activity feed: the newest entries of the same list the Activity Log tab shows in full. --}}
@php $r = $it['recent']; @endphp
<section class="mfd-card itd-card" aria-labelledby="itdBriefTitle" data-it-brief>
    <div class="mfd-card-head">
        <div><h2 id="itdBriefTitle">Recent System Activity</h2><p class="itd-sub">The latest actions people took in iClimate</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now']])</div>
        <a class="mfd-link" href="{{ route('admin.dashboard', ['tab' => 'activity']) }}#itd-recent" data-tab-link="activity" data-target="itd-recent">View all</a>
    </div>
    @if ($r['state'] === 'error')
        @include('dashboards.it._state', ['kind' => 'error', 'text' => $r['message']])
    @elseif ($r['state'] === 'empty')
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'Activity will show here as people sign in and use the system.'])
    @else
        <ul class="itd-feed">
            @foreach (array_slice($r['rows'], 0, 6) as $row)
                <li>
                    <div class="itd-feed-text"><strong>{{ $row['action'] }}</strong><span>{{ $row['user'] }} · {{ $row['role'] }}</span></div>
                    <time datetime="{{ $row['at']->toIso8601String() }}">{{ $row['at']->format('M j, g:i A') }}</time>
                </li>
            @endforeach
        </ul>
    @endif
</section>
