@php $i = $it['integrity']; @endphp
<section class="mfd-card itd-card is-{{ ($i['state'] ?? 'error') === 'ok' && ($i['account_gap'] || $i['without_profile'] || $i['without_org'] || $i['missing_fields'] || $i['orphaned']) ? 'warn' : 'ok' }}" aria-labelledby="itdIntTitle" data-it-integrity>
    <div class="mfd-card-head"><div><h2 id="itdIntTitle">Data Integrity</h2><p class="itd-sub">Records that need fixing, each with a link to the list</p>@include('dashboards.it._updated', ['at' => $it['stamps']['integrity'] ?? $it['stamps']['now']])</div></div>
    @if (($i['state'] ?? 'error') === 'error')
        @include('dashboards.it._state', ['kind' => 'error', 'text' => 'Unable to run the integrity checks.'])
    @else
        @if ($i['account_gap'] !== 0)
            <div class="mfd-note is-watch" role="alert" data-integrity-gap>@include('dashboards.mao.icon', ['name' => 'alert'])<p><strong>Warning:</strong> {{ number_format($i['farmer_accounts']) }} farmer accounts but {{ number_format($i['farmer_profiles']) }} farmer profile records ({{ number_format(abs($i['account_gap'])) }} {{ $i['account_gap'] > 0 ? 'accounts without a profile' : 'more profiles than accounts' }}).</p></div>
        @endif
        @php
            $items = [
                ['Farmer accounts without a profile', $i['without_profile'], route('users.index', ['integrity' => 'farmers_without_profile'])],
                ['Farmers without an organization', $i['without_org'], route('farmer-profiles.index', ['integrity' => 'no_organization'])],
                ['Profiles with missing required fields', $i['missing_fields'], route('farmer-profiles.index', ['integrity' => 'missing_fields'])],
                ['Orphaned records', $i['orphaned'], route('farmer-profiles.index', ['integrity' => 'orphaned'])],
            ];
        @endphp
        <dl class="mfd-facts itd-rows">
            @foreach ($items as [$label, $count, $href])
                <div><dt>{{ $label }}</dt><dd>
                    @if ($count > 0)@include('dashboards.it._badge', ['level' => 'warn', 'text' => number_format($count)]) <a class="mfd-link" href="{{ $href }}">Fix</a>
                    @else @include('dashboards.it._badge', ['level' => 'ok', 'text' => '0'])@endif
                </dd></div>
            @endforeach
        </dl>
    @endif
</section>
