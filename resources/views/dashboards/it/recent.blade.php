@php
    $r = $it['recent'];
    $when = fn ($t) => $t->timezone('Asia/Manila')->isToday() ? $t->timezone('Asia/Manila')->format('g:i A') : $t->timezone('Asia/Manila')->format('M j, g:i A');
@endphp
<section class="itd-card" aria-labelledby="itdRecentTitle" data-it-recent>
    <div class="itd-card-head">
        <h2 id="itdRecentTitle">Recent System Activity</h2>
        <a class="itd-btn is-quiet" href="{{ route('system-logs.index') }}">View All Logs</a>
    </div>
    @if ($r['state'] === 'error')
        <p class="itd-error" role="alert">{{ $r['message'] }}</p>
    @elseif ($r['state'] === 'empty')
        <p class="itd-empty">No system activity has been recorded yet.</p>
    @else
        <div class="itd-table-wrap">
            <table class="itd-table itd-table-cards">
                <thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Role</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($r['rows'] as $row)
                        <tr>
                            <td data-label="Time" class="itd-nowrap">{{ $when($row['at']) }}</td>
                            <td data-label="User">{{ $row['user'] }}</td>
                            <td data-label="Role">{{ $row['role'] }}</td>
                            <td data-label="Action"><strong>{{ $row['action'] }}</strong></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
