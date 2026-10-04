@php $r = $it['recent']; @endphp
@if ($r['state'] === 'error')
    @include('dashboards.it._state', ['kind' => 'error', 'text' => $r['message']])
@elseif ($r['state'] === 'empty')
    @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No activity matches these filters.'])
@else
    <div class="itd-table-wrap">
        <table class="itd-table is-wide">
            <thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Role</th><th scope="col">Action</th><th scope="col">IP address</th></tr></thead>
            <tbody>
                @foreach ($r['rows'] as $row)
                    <tr class="{{ $row['flag'] ? 'is-flag-'.$row['flag'] : '' }}">
                        <td class="itd-mono itd-nowrap">{{ $row['at']->format('M j, g:i A') }}</td>
                        <td>{{ $row['user'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>
                            <strong>{{ $row['action'] }}</strong>
                            @if ($row['flag'] === 'failed')@include('dashboards.it._badge', ['level' => 'crit', 'text' => 'Failed login'])
                            @elseif ($row['flag'] === 'privileged')@include('dashboards.it._badge', ['level' => 'warn', 'text' => 'Privileged'])@endif
                        </td>
                        <td class="itd-mono">{{ $row['ip'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
