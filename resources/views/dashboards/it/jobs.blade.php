@php $jobs = $it['extra']['failed_jobs']; @endphp
<section class="mfd-card itd-card" aria-labelledby="itdJobsTitle" data-it-failed-jobs>
    <div class="mfd-card-head">
        <div><h2 id="itdJobsTitle">Background Jobs to Retry</h2><p class="itd-sub">Jobs that did not finish, grouped by type (top 5 of the most recent 1,000)</p>@include('dashboards.it._updated', ['at' => $it['stamps']['extra'] ?? $it['stamps']['now']])</div>
    </div>
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
