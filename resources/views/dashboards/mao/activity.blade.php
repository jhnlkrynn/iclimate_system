@php
    $a = $mao['activity'];
    $when = fn ($t) => $t->timezone('Asia/Manila')->isToday() ? $t->timezone('Asia/Manila')->format('g:i A') : $t->timezone('Asia/Manila')->format('M j, g:i A');
@endphp
@if (($a['state'] ?? null) === 'ok')
    <section class="mtd-card" aria-labelledby="mtdActivityTitle" data-mao-activity>
        <div class="mtd-card-head">
            <h2 id="mtdActivityTitle">Recent MAO Activity</h2>
            <a class="mtd-btn is-quiet" href="{{ route('mao.operations') }}#activity-log" data-ic-view data-ic-select="#activity-log" data-ic-title="MAO Activity" aria-haspopup="dialog">View All Activity</a>
        </div>
        <div class="mtd-table-wrap">
            <table class="mtd-table mtd-table-cards">
                <thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($a['rows'] as $row)
                        <tr>
                            <td data-label="Time" class="mtd-nowrap">{{ $when($row['at']) }}</td>
                            <td data-label="User">{{ $row['user'] }}</td>
                            <td data-label="Action"><strong>{{ $row['action'] }}</strong>@if ($row['detail'] && $row['detail'] !== $row['action'].'.') <span class="mtd-muted">— {{ $row['detail'] }}</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endif
