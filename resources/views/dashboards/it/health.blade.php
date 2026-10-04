@php $jobs = $it['extra']['failed_jobs']; @endphp
<section class="mfd-card itd-card is-{{ $it['overall']['level'] }}" aria-labelledby="itdHealthTitle" data-it-health>
    <div class="mfd-card-head">
        <div><h2 id="itdHealthTitle">System Health</h2><p class="itd-sub">Seven live checks, plus background jobs that need a retry</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now']])</div>
        <a class="mfd-link" href="{{ route('system-health.index') }}">Full diagnostics</a>
    </div>
    <div class="itd-table-wrap">
        <table class="itd-table is-wide">
            <thead><tr><th scope="col">Service</th><th scope="col">Status</th><th scope="col">Detail</th><th scope="col">Last Checked</th></tr></thead>
            <tbody>
                @foreach ($it['services'] as $s)
                    <tr data-service="{{ $s['key'] }}" data-level="{{ $s['level'] }}">
                        <td><strong>{{ $s['name'] }}</strong></td>
                        <td>@include('dashboards.it._badge', ['level' => $s['level']])</td>
                        <td>{{ $s['detail'] }}</td>
                        <td class="itd-mono itd-nowrap">{{ $s['checked_at']->format('g:i:s A') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h3 class="itd-h3" data-it-failed-jobs>Failed jobs by class <span class="itd-period">top 5 · most recent 1,000 failures</span></h3>
    @if ($jobs['state'] === 'unavailable')
        @include('dashboards.it._state', ['kind' => 'empty', 'text' => 'The failed-jobs table could not be read.'])
    @elseif ($jobs['state'] === 'empty')
        @include('dashboards.it._state', ['kind' => 'good', 'text' => 'No failed jobs. Nothing is waiting for a retry.'])
    @else
        <div class="itd-table-wrap">
            <table class="itd-table">
                <thead><tr><th scope="col">Job class</th><th scope="col" class="itd-num">Failures</th><th scope="col">Latest</th><th scope="col">Action</th></tr></thead>
                <tbody>
                    @foreach ($jobs['rows'] as $j)
                        <tr>
                            <td class="itd-mono">{{ $j['class'] }}</td>
                            <td class="itd-num">{{ number_format($j['count']) }}</td>
                            <td class="itd-mono itd-nowrap">{{ $j['latest']?->format('M j, g:i A') }}</td>
                            <td>
                                <form method="POST" action="{{ route('admin.dashboard.retry-failed') }}" data-ic-confirm="Retry {{ $j['count'] }} failed {{ $j['count'] === 1 ? 'job' : 'jobs' }} of class {{ $j['class'] }}? They will be put back on the queue and the retry will be recorded in the system log.">@csrf
                                    <input type="hidden" name="class" value="{{ $j['class'] }}">
                                    <button type="submit" class="mfd-btn is-quiet is-small">Retry</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
