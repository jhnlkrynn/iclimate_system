@php
    $r = $it['resources'];
    $svc = app(\App\Services\Analytics\ItDashboardService::class);
    $disk = $r['disk'];
    $level = $disk ? ($disk['pct'] >= 95 ? 'crit' : ($disk['pct'] >= 85 ? 'warn' : 'ok')) : 'unk';
    $tables = $it['extra']['tables'];
    $up = $it['extra']['uploads'];
@endphp
<section class="itd-card" aria-labelledby="itdResTitle" data-it-resources>
    <div class="itd-card-head"><div><h2 id="itdResTitle">Resources</h2><p class="itd-sub">Database, disk, uploads and release information</p>@include('dashboards.it._updated', ['at' => $it['stamps']['resources'] ?? $it['stamps']['now']])</div></div>
    <dl class="itd-rows">
        <div><dt>Database size</dt><dd class="itd-num">{{ $svc->bytes($r['db_size']) }}</dd></div>
        <div>
            <dt>Storage / disk usage</dt>
            <dd class="itd-num">
                @if ($disk)
                    <span class="itd-meter is-{{ $level }}" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $disk['pct'] }}" aria-label="Disk usage"><span style="width: {{ min(100, $disk['pct']) }}%"></span></span>
                    @include('dashboards.it._badge', ['level' => $level, 'text' => $disk['pct'].'% used'])
                @else Not available @endif
            </dd>
        </div>
        <div><dt>Uploads storage</dt><dd class="itd-num">{{ $svc->bytes($r['uploads']) }}</dd></div>
        <div><dt>App version</dt><dd class="itd-mono">{{ $r['version'] ?? 'Not available' }}</dd></div>
        <div><dt>Last deployment</dt><dd class="itd-mono">{{ $r['deployed_at'] ? $r['deployed_at']->format('M j, Y g:i A') : 'Not available' }}</dd></div>
    </dl>

    <h3 class="itd-h3" data-it-tables>Largest tables <span class="itd-period">top 5 by row count</span></h3>
    @if (count($tables) === 0)
        <p class="itd-empty">Table sizes cannot be read on this database.</p>
    @else
        <dl class="itd-rows">
            @foreach ($tables as $t)
                <div><dt class="itd-mono">{{ $t['table'] }}</dt><dd class="itd-num">{{ number_format($t['rows']) }} <span class="itd-unit">rows</span></dd></div>
            @endforeach
        </dl>
    @endif

    <h3 class="itd-h3" data-it-uploads>Uploads added <span class="itd-period">last 30 days · Community Feed and Messages</span></h3>
    @if (($up['state'] ?? 'unavailable') === 'unavailable')
        <p class="itd-empty">Upload records cannot be read.</p>
    @elseif ($up['state'] === 'empty')
        <p class="itd-empty">No files were uploaded in the last 30 days.</p>
    @else
        <div class="itd-spark-row">@include('dashboards.it._spark', ['counts' => $up['counts'], 'label' => 'Files uploaded per day'])</div>
        <dl class="itd-rows">
            <div><dt>Community Feed files</dt><dd class="itd-num">{{ number_format($up['feed_files']) }} <span class="itd-unit">{{ $svc->bytes($up['feed_bytes']) }}</span></dd></div>
            <div><dt>Message attachments</dt><dd class="itd-num">{{ number_format($up['message_files']) }} <span class="itd-unit">files</span></dd></div>
        </dl>
    @endif
    <p class="itd-foot">Database growth over time is not tracked yet.</p>
</section>
