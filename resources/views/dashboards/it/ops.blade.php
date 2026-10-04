@php
    $sched = $it['extra']['scheduler'];
    $err = $it['extra']['errors'];
    $statusLabel = ['succeeded' => ['ok', 'Succeeded'], 'failed' => ['crit', 'Failed'], 'running' => ['warn', 'Running']];
@endphp
<section class="mfd-card itd-card" aria-labelledby="itdOpsTitle" data-it-ops>
    <div class="mfd-card-head">
        <div><h2 id="itdOpsTitle">Scheduled Tasks and Application Errors</h2><p class="itd-sub">What the scheduler ran and what the application logged</p>@include('dashboards.it._updated', ['at' => $it['stamps']['extra'] ?? $it['stamps']['now']])</div>
        <a class="mfd-link" href="{{ route('system-health.index') }}">Full diagnostics</a>
    </div>

    <h3 class="itd-h3 is-first" data-it-scheduler>Scheduled tasks <span class="itd-period">last run from the task history · next run from the schedule</span></h3>
    @if ($sched['state'] !== 'ok')
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => $sched['state'] === 'empty' ? 'No scheduled tasks are defined.' : 'The schedule could not be read.'])
    @else
        <div class="itd-table-wrap">
            <table class="itd-table is-wide">
                <thead><tr><th scope="col">Task</th><th scope="col">Last run</th><th scope="col">Next run</th><th scope="col">Status</th></tr></thead>
                <tbody>
                    @foreach ($sched['rows'] as $t)
                        <tr>
                            <td class="itd-mono">{{ $t['name'] }}</td>
                            <td class="itd-mono itd-nowrap">{{ $t['last'] ? $t['last']->format('M j, g:i A') : 'Not run yet' }}</td>
                            <td class="itd-mono itd-nowrap">{{ $t['next']->format('M j, g:i A') }}</td>
                            <td>
                                @if ($t['status'])@include('dashboards.it._badge', ['level' => $statusLabel[$t['status']][0] ?? 'unk', 'text' => $statusLabel[$t['status']][1] ?? ucfirst($t['status'])])@else @include('dashboards.it._badge', ['level' => 'unk', 'text' => 'Not run yet'])@endif
                                @if ($t['status'] === 'failed' && $t['summary'])<small class="d-block itd-mono">{{ \Illuminate\Support\Str::limit($t['summary'], 90) }}</small>@endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h3 class="itd-h3" data-it-app-errors>Application errors <span class="itd-period">from the daily application log</span></h3>
    @if ($err['state'] === 'empty')
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'No application log file was found yet. Errors will appear here once the application writes to its log.'])
    @else
        <div class="itd-table-wrap">
            <table class="itd-table">
                <thead><tr><th scope="col">Level</th><th scope="col" class="itd-num">Last 24 hours</th><th scope="col" class="itd-num">Last 7 days</th></tr></thead>
                <tbody>
                    @foreach (['critical' => ['crit', 'Critical'], 'error' => ['warn', 'Error'], 'warning' => ['unk', 'Warning']] as $lvl => [$tone, $name])
                        <tr><td>@include('dashboards.it._badge', ['level' => $err['d7'][$lvl] > 0 ? $tone : 'ok', 'text' => $name])</td><td class="itd-num">{{ number_format($err['h24'][$lvl]) }}</td><td class="itd-num">{{ number_format($err['d7'][$lvl]) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if (count($err['top']))
            <p class="itd-minor">Most repeated errors (7 days)</p>
            <ul class="itd-list">
                @foreach ($err['top'] as $e)
                    <li><span class="itd-mono">{{ $e['message'] }}</span> @include('dashboards.it._badge', ['level' => 'warn', 'text' => number_format($e['count']).'×'])</li>
                @endforeach
            </ul>
        @else
            @include('dashboards.it._state', ['kind' => 'good', 'text' => 'No errors in the last 7 days.'])
        @endif
    @endif
</section>
