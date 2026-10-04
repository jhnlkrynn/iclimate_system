<section class="mfd-card itd-card" aria-labelledby="itdRecentTitle" data-it-recent>
    <div class="mfd-card-head">
        <div><h2 id="itdRecentTitle">Recent System Activity</h2><p class="itd-sub">Latest 10 audit entries; failed logins and privileged actions are highlighted</p>@include('dashboards.it._updated', ['at' => $it['stamps']['now']])</div>
        <a class="mfd-btn is-quiet" href="{{ route('system-logs.index') }}">@include('dashboards.mao.icon', ['name' => 'report'])View All Logs</a>
    </div>
    <form class="itd-filters" data-recent-filters aria-label="Filter recent activity">
        <label>Role
            <select name="role"><option value="">All roles</option>@foreach ($it['filters']['roles'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </label>
        <label>Action type
            <select name="action"><option value="">All actions</option>@foreach ($it['filters']['actions'] as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select>
        </label>
        <label>From <input type="date" name="from"></label>
        <label>To <input type="date" name="to"></label>
    </form>
    <div data-recent-body>@include('dashboards.it.recent-rows')</div>
</section>
