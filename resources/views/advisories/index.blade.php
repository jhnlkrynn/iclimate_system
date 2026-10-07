<x-app-layout>
    @include('advisories._analytics-status')
    {{-- Advisories (2026-10, PlantingAdvisoryController::index): one list, not a calendar. Each item: a severity colour,
         the barangay, the valid dates, one sentence of recommendation and one "What to do". Planting is shown first;
         another type is a chip only when an advisory of that type exists. Farmers read their own barangay and
         Lian-wide advice in their language; MAO and IT also see what waits for approval. --}}
    @php
        $t = fn (string $key, array $replace = []) => trans('farmer.advisories_page.'.$key, $replace, $locale);
        $typeLabel = fn (string $key) => trans()->has('farmer.advisories_page.types.'.$key, $locale) ? $t('types.'.$key) : str($key)->headline();
        $url = fn (array $change) => route('planting-advisories.index', array_filter(array_merge(
            ['type' => $type === 'planting' ? null : $type, 'show' => $show === 'active' ? null : $show, 'target_barangay' => $isFarmer ? null : $barangay],
            $change,
        ), fn ($v) => $v !== null && $v !== ''));
        $views = ['active' => 'Active', 'pending' => 'To approve', 'all' => 'All'];
        $statusTone = ['published' => 'good', 'pending_review' => 'watch', 'expired' => 'muted', 'rejected' => 'danger', 'archived' => 'muted', 'scheduled' => 'watch'];   // Manuscript – Naicel: + scheduled alerts
    @endphp

    <div class="fad adv" lang="{{ $locale }}">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $t('kicker') }}</span>
                <h1>{{ $t('title') }}</h1>
                <p>{{ $isFarmer ? $t('intro') : 'Advice published to farmers, by barangay. Review the system\'s recommendation, then approve and publish.' }}</p>
            </div>
            @if ($canManage)
                <div class="fad-hero-actions">
                    <a class="fad-btn is-light" href="{{ route('planting-advisories.create', array_filter(['barangay' => $barangay])) }}">@include('dashboards.mao.icon', ['name' => 'plus'])<span>New Advisory</span></a>
                    {{-- Manuscript – Naicel (2026-10-08): typhoon / flood / drought / heat alerts, now or scheduled, with read counts. --}}
                    @if (\Illuminate\Support\Facades\Route::has('mao.alerts.index'))
                        <a class="fad-btn is-light" href="{{ route('mao.alerts.index') }}" data-send-alert>@include('dashboards.mao.icon', ['name' => 'plus'])<span>Send Alert</span></a>
                    @endif
                </div>
            @endif
        </header>

        @if (! $isFarmer)
            {{-- Staff: what farmers see now, what waits for approval, or everything; and one barangay. --}}
            <div class="adv-bar">
                <nav class="adv-chips" aria-label="Show advisories">
                    @foreach ($views as $key => $label)
                        <a href="{{ $url(['show' => $key === 'active' ? null : $key, 'type' => null]) }}" @class(['adv-chip', 'is-active' => $show === $key]) @if ($show === $key) aria-current="page" @endif data-adv-show="{{ $key }}">
                            {{ $label }}@if ($key === 'pending' && $pendingCount > 0)<span class="fad-count" aria-label="{{ $pendingCount }} to approve">{{ $pendingCount }}</span>@endif
                        </a>
                    @endforeach
                </nav>
                <form class="adv-filter" method="GET" action="{{ route('planting-advisories.index') }}" data-adv-filter>
                    @if ($show !== 'active')<input type="hidden" name="show" value="{{ $show }}">@endif
                    @if ($type !== 'planting')<input type="hidden" name="type" value="{{ $type }}">@endif
                    <label class="fad-label" for="advBarangay">Barangay</label>
                    <select class="fad-input" id="advBarangay" name="target_barangay" onchange="this.form.submit()">
                        <option value="">All barangays</option>
                        @foreach ($barangays as $name)
                            <option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>
                        @endforeach
                    </select>
                    <noscript><button class="fad-btn is-quiet is-compact" type="submit">Show</button></noscript>
                </form>
            </div>
        @elseif (! $farmerBarangay)
            <p class="fad-support" data-adv-no-barangay>{{ $t('no_barangay') }} <a class="fad-link" href="{{ route('profile.edit') }}">{{ __('farmer.my_farm_page.edit_profile', [], $locale) }}</a></p>
        @endif

        {{-- What the last scheduled check found. An unreachable source is said so, never shown as "no advisories". --}}
        {{-- Farmer – Naicel (2026-10): a farmer with no advisories sees only the page title: no "No data yet", no empty box. --}}
        @if ($check['state'] === 'unchecked' && $isFarmer)
        @elseif ($check['state'] === 'unchecked')
            {{-- Nothing has been checked yet: the same calm "no data yet" as every other page. --}}
            <x-no-data :label="$isFarmer ? trans('farmer.home.no_data', [], $locale) : 'Walang datos pa'" :note="$t('check.unchecked')" data-adv-check="unchecked" />
        @elseif ($check['state'] !== 'ok')
            <p class="fad-support adv-check" role="status" data-adv-check="{{ $check['state'] }}">{{ $t('check.'.$check['state']) }}</p>
        @endif
        @if ($check['checked_at'])
            <p class="adv-updated"><x-updated-at :at="$check['checked_at']" :label="trans('farmer.home.updated', [], $locale)" /></p>
        @endif

        {{-- Planting first; another kind only when it is in use. --}}
        @if ($types->count() > 1)
            <nav class="adv-chips" aria-label="{{ $t('types_label') }}" data-adv-types>
                @foreach ($types as $key => $count)
                    <a href="{{ $url(['type' => $key === 'planting' ? null : $key]) }}" @class(['adv-chip', 'is-active' => $type === $key]) @if ($type === $key) aria-current="page" @endif data-adv-type="{{ $key }}">
                        {{ $typeLabel($key) }}<span class="adv-chip-count">{{ $count }}</span>
                    </a>
                @endforeach
            </nav>
        @endif

        <section class="adv-section" aria-labelledby="advListTitle">
            <h2 id="advListTitle" class="visually-hidden">{{ $typeLabel($type) }}</h2>
            @if ($items->isNotEmpty())
                <ul class="adv-list" data-adv-list>
                    @foreach ($items as $item)
                        @php $a = $item['advisory']; @endphp
                        <li class="adv-item is-{{ $item['level'] }}" data-adv-item="{{ $a->id }}">
                            <div class="adv-top">
                                <span class="adv-sev is-{{ $item['level'] }}">{{ $item['level_label'] }}</span>
                                <span class="adv-area">{{ $item['area'] }}</span>
                                @if ($item['valid'])<span class="adv-valid"><span class="adv-valid-label">{{ $t('valid') }}:</span> {{ $item['valid'] }}</span>@endif
                                @if (! $isFarmer)<span class="fad-badge is-{{ $statusTone[$a->status] ?? 'muted' }} adv-status">{{ $a->statusLabel() }}</span>@endif
                            </div>
                            {{-- The title is for screen readers (one heading per item); the row itself stays the five facts. --}}
                            <h3 class="adv-title visually-hidden">{{ $a->title }}</h3>
                            <p class="adv-rec">{{ $item['recommendation'] }}</p>
                            <p class="adv-do"><strong>{{ $t('what_to_do') }}:</strong> {{ $item['action'] }}</p>
                            @if ($item['activity'])
                                <p class="adv-activity"><strong>{{ $t('activity') }}:</strong> {{ $item['activity'] }}@if ($item['activity_date']) · {{ $item['activity_date'] }}@endif</p>
                            @endif
                            <div class="adv-foot">
                                <span class="adv-source">{{ $item['official'] ? $t('official') : $item['source'] }}</span>
                                <span class="adv-actions">
                                    {{-- data-fast-nav-bound: the site's link prefetch skips this link; the pop-up handles it. --}}
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('planting-advisories.show', $a) }}" data-fad-view data-fast-nav-bound="true" aria-haspopup="dialog" aria-label="{{ $t('details_label', ['area' => $item['area']]) }}">{{ $t('details') }}</a>
                                    @if ($canManage)
                                        @if ($a->status === \App\Models\PlantingAdvisory::STATUS_PENDING_REVIEW)
                                            <a class="fad-btn is-primary is-compact" href="{{ route('planting-advisories.edit', $a) }}">Review and publish</a>
                                        @else
                                            <a class="fad-btn is-quiet is-compact" href="{{ route('planting-advisories.edit', $a) }}">Edit</a>
                                        @endif
                                    @endif
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @elseif (! $isFarmer && ($show !== 'active' || ! in_array($check['state'], ['unchecked', 'unavailable'], true)))
                {{-- Only a finished check may say "none right now". --}}
                <div class="fad-empty" data-adv-empty>
                    <p class="fad-lead">{{ $show === 'pending' ? 'Nothing is waiting for approval.' : $t($type === 'planting' ? 'empty.planting' : 'empty.other') }}</p>
                    <p class="fad-body">{{ $isFarmer ? $t('empty_note') : 'Use New Advisory to review the system\'s recommendation for a barangay and publish it.' }}</p>
                </div>
            @endif
        </section>

        @if ($isFarmer)
            @include('advisories._announcements', ['announcements' => $announcements ?? collect()])
        @elseif ($checkIns !== null)
            @include('advisories._check-ins', ['checkIns' => $checkIns])
        @endif
    </div>

    {{-- One pop-up for every advisory; its content comes from the advisory page (?modal=1). --}}
    @include('partials.fad-modal-shell')
</x-app-layout>
