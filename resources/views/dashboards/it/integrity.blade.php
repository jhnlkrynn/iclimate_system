@php $i = $it['integrity']; @endphp
<section class="itd-card is-{{ ($i['state'] ?? 'error') === 'ok' && ($i['account_gap'] || $i['without_profile'] || $i['without_org'] || $i['missing_fields'] || $i['orphaned']) ? 'warn' : 'ok' }}" aria-labelledby="itdIntTitle" data-it-integrity>
    <div class="itd-card-head"><div><h2 id="itdIntTitle">Data Integrity</h2><p class="itd-sub">Records that need fixing, each with a link to the list</p>@include('dashboards.it._updated', ['at' => $it['stamps']['integrity'] ?? $it['stamps']['now']])</div></div>
    @if (($i['state'] ?? 'error') === 'error')
        <p class="itd-error" role="alert">Unable to run the integrity checks.</p>
    @else
        @if ($i['account_gap'] !== 0)
            <p class="itd-warning" role="alert" data-integrity-gap><span aria-hidden="true">▲</span> <strong>Warning:</strong> {{ number_format($i['farmer_accounts']) }} farmer accounts but {{ number_format($i['farmer_profiles']) }} farmer profile records ({{ number_format(abs($i['account_gap'])) }} {{ $i['account_gap'] > 0 ? 'accounts without a profile' : 'more profiles than accounts' }}).</p>
        @endif
        @php
            $items = [
                ['Farmer accounts without a profile', $i['without_profile'], route('users.index', ['integrity' => 'farmers_without_profile'])],
                ['Farmers without an organization', $i['without_org'], route('farmer-profiles.index', ['integrity' => 'no_organization'])],
                ['Profiles with missing required fields', $i['missing_fields'], route('farmer-profiles.index', ['integrity' => 'missing_fields'])],
                ['Orphaned records', $i['orphaned'], route('farmer-profiles.index', ['integrity' => 'orphaned'])],
            ];
        @endphp
        <dl class="itd-rows">
            @foreach ($items as [$label, $count, $href])
                <div><dt>{{ $label }}</dt><dd class="itd-num">
                    @if ($count > 0)<span class="itd-badge is-warn"><span aria-hidden="true">▲</span> {{ number_format($count) }}</span> <a class="itd-link" href="{{ $href }}">Fix</a>
                    @else<span class="itd-badge is-ok"><span aria-hidden="true">✓</span> 0</span>@endif
                </dd></div>
            @endforeach
        </dl>
    @endif
</section>
