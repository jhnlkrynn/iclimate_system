@php $r = $it['recent']; @endphp
@if ($r['state'] === 'error')
    <p class="itd-error" role="alert">{{ $r['message'] }}</p>
@elseif ($r['state'] === 'empty')
    <p class="itd-empty">No activity matches these filters.</p>
@else
    <div class="itd-table-wrap">
        <table class="itd-table">
            <thead><tr><th scope="col">Time</th><th scope="col">User</th><th scope="col">Role</th><th scope="col">Action</th><th scope="col">IP address</th></tr></thead>
            <tbody>
                @foreach ($r['rows'] as $row)
                    <tr class="{{ $row['flag'] ? 'is-flag-'.$row['flag'] : '' }}">
                        <td class="itd-mono itd-nowrap">{{ $row['at']->format('M j, g:i A') }}</td>
                        <td>{{ $row['user'] }}</td>
                        <td>{{ $row['role'] }}</td>
                        <td>
                            <strong>{{ $row['action'] }}</strong>
                            @if ($row['flag'] === 'failed')<span class="itd-badge is-crit"><span aria-hidden="true">✕</span> Failed login</span>
                            @elseif ($row['flag'] === 'privileged')<span class="itd-badge is-warn"><span aria-hidden="true">▲</span> Privileged</span>@endif
                        </td>
                        <td class="itd-mono">{{ $row['ip'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
