<x-app-layout>
    {{-- "Aking Bukid" (FarmerMyFarmController): the farmer's own farm, planting schedule, harvest records (printable)
         and upcoming dates. Same look as the other farmer pages (.fad / .frec). --}}
    @php
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('M j, Y') : '—';
    @endphp

    <div class="fad frec fmf" data-my-farm data-farm-realtime-user-id="{{ auth()->id() }}" data-farm-realtime-farm-id="{{ $current?->getKey() ?? '' }}">
        {{-- Printed on paper only (Print button): the office name and the print date. --}}
        <div class="fmf-print-head">
            <strong>{{ __('farmer.my_farm_page.print_header') }}</strong>
            <span>{{ __('farmer.my_farm_page.title') }} · {{ auth()->user()->name }}</span>
            <span>{{ __('farmer.my_farm_page.printed', ['date' => $printedAt->format('M j, Y g:i A')]) }}</span>
        </div>

        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ __('farmer.nav.my_farm') }}</span>
                <h1>{{ __('farmer.my_farm_page.title') }}</h1>
                <p>{{ __('farmer.my_farm_page.intro') }}</p>
            </div>
            <div class="fad-hero-actions fmf-no-print">
                <button type="button" class="fad-btn is-light" data-print>{{ __('farmer.my_farm_page.print') }}</button>
                {{-- Manuscript – Naicel (2026-10-08): My Reports (prediction and advisory summaries, PDF). --}}
                @if (\Illuminate\Support\Facades\Route::has('farmer.reports.index'))
                    <a class="fad-btn is-light" href="{{ route('farmer.reports.index') }}" data-my-reports-link>{{ __('my_reports.title') }}</a>
                @endif
            </div>
        </header>

        @if (\App\Support\FarmerProfileCompletion::isIncomplete(auth()->user()))
            @include('partials.complete-profile-banner', ['dismissible' => false])
        @endif

        @if ($available)
            {{-- My Farms: every farm of this farmer, each with its own location and size. --}}
            <section class="frec-panel fmf-no-print" aria-labelledby="ffmListTitle" data-my-farms>
                <div class="frec-panel-head is-row">
                    <h2 id="ffmListTitle">{{ __('farms.title') }}</h2>
                    <a class="fad-btn is-compact" href="{{ route('farmer.farms.create') }}">+ {{ $farms ? __('farms.add_another') : __('farms.add') }}</a>
                </div>
                @if ($farms)
                    <p class="fad-m-small mb-0">{{ __('farms.intro') }}</p>
                    <ul class="ffm-cards">
                        @foreach ($farms as $f)
                            <li class="ffm-card @if ($f['current']) is-current @endif" data-farm-card="{{ $f['id'] }}">
                                <div class="ffm-card-head">
                                    <h3>{{ $f['name'] }}</h3>
                                    <div class="ffm-badges">
                                        @if ($f['primary'])<span class="fad-badge is-good">{{ __('farms.primary') }}</span>@endif
                                        @if ($f['current'])<span class="fad-badge is-info">{{ __('farms.current') }}</span>@endif
                                        @if (($f['verification_status'] ?? 'approved') === 'pending')
                                            <span class="fad-badge is-watch" data-farm-verification-badge="pending">{{ app()->getLocale() === 'fil' ? 'Naghihintay ng pagsusuri ng MAO' : 'Pending MAO review' }}</span>
                                        @elseif (($f['verification_status'] ?? 'approved') === 'rejected')
                                            <span class="fad-badge is-danger" data-farm-verification-badge="rejected">{{ app()->getLocale() === 'fil' ? 'Tinanggihan' : 'Rejected' }}</span>
                                        @else
                                            <span class="fad-badge is-good" data-farm-verification-badge="approved">{{ app()->getLocale() === 'fil' ? 'Aprubado' : 'Approved' }}</span>
                                        @endif
                                    </div>
                                </div>
                                @if (($f['verification_status'] ?? 'approved') === 'rejected' && ! empty($f['rejection_reason']))
                                    <p class="small text-danger mb-1" data-farm-rejection-reason>{{ app()->getLocale() === 'fil' ? 'Dahilan:' : 'Reason:' }} {{ $f['rejection_reason'] }}</p>
                                @endif
                                <p class="ffm-card-place">{{ $f['barangay'] ? __('farms.location_line', ['barangay' => $f['barangay']]) : __('farms.no_barangay') }}@if ($f['location']) · {{ $f['location'] }}@endif</p>
                                <p class="ffm-card-size">{{ $f['size']['text'] }}@if ($f['size']['note'])<small>{{ $f['size']['note'] }}</small>@endif</p>
                                @if ($f['association'])
                                    <p class="ffm-card-assoc" data-farm-association="{{ $f['association']['state'] }}">
                                        {{ __('farms.association.card.'.$f['association']['state'], ['name' => $f['association']['name']]) }}
                                        @if ($f['association']['note'] && $f['association']['state'] === 'not_member')<small class="d-block fad-m-small">{{ __('farms.association.card.note', ['note' => $f['association']['note']]) }}</small>@endif
                                        @if ($f['association']['set_by_mao'] ?? false)<small class="d-block fad-m-small" data-association-set-by-mao>{{ __('farms.association.card.set_by_mao') }}</small>@endif
                                    </p>
                                @endif
                                <div class="ffm-card-actions">
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('farmer.farms.edit', $f['id']) }}">
                                        {{ ($f['verification_status'] ?? 'approved') === 'rejected' ? (app()->getLocale() === 'fil' ? 'Ayusin at ipasa muli' : 'Fix and resubmit') : __('farms.edit') }}
                                    </a>
                                    <form method="POST" action="{{ route('farmer.farms.destroy', $f['id']) }}"
                                        data-ic-confirm="{{ count($farms) === 1 ? __('farms.remove_only_confirm', ['name' => $f['name']]) : __('farms.remove_confirm', ['name' => $f['name']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="fad-btn is-quiet is-compact ffm-remove">
                                            {{ ($f['verification_status'] ?? 'approved') === 'pending' ? (app()->getLocale() === 'fil' ? 'Kanselahin' : 'Cancel submission') : __('farms.remove') }}
                                        </button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="ffm-empty">
                        <strong>{{ __('farms.add_first') }}</strong>
                        <p class="fad-m-small mb-0">{{ __('farms.add_first_note') }}</p>
                        <a class="fad-btn" href="{{ route('farmer.farms.create') }}">+ {{ __('farms.add') }}</a>
                    </div>
                @endif
            </section>
            {{-- A farm without an association answer: asked here, under the farm list, one farm at a time
                 (it used to sit at the top of the Farmer Dashboard). --}}
            @if (! empty($associationPrompt))
                @include('dashboards.farmer.association-prompt', ['prompt' => $associationPrompt])
            @endif
            {{-- Archived farms (removed, or archived when the account was closed): Restore brings one back, plans and map included. --}}
            @if (($archivedFarms ?? collect())->isNotEmpty())
                <section class="frec-panel fmf-no-print" aria-labelledby="ffmArchivedTitle" data-archived-farms>
                    <div class="frec-panel-head"><h2 id="ffmArchivedTitle">{{ __('farms.archived_title') }}</h2></div>
                    <p class="fad-m-small mb-0">{{ __('farms.archived_intro') }}</p>
                    <ul class="ffm-list">
                        @foreach ($archivedFarms as $archived)
                            <li class="ffm-card" data-archived-farm="{{ $archived->id }}">
                                <div class="ffm-card-head"><strong class="ffm-card-name">{{ $archived->farm_name }}</strong></div>
                                <p class="ffm-card-place">{{ $archived->barangay ? __('farms.location_line', ['barangay' => \App\Services\Risk\HeatMapPanel::displayName($archived->barangay)]) : __('farms.no_barangay') }}</p>
                                <div class="ffm-card-actions">
                                    <form method="POST" action="{{ route('farmer.farms.restore', $archived->id) }}">
                                        @csrf
                                        <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farms.restore') }}</button>
                                    </form>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @endif

        @if (! $available || $current)
            <section class="frec-panel" aria-labelledby="fmfInfoTitle">
                <div class="frec-panel-head is-row">
                    <h2 id="fmfInfoTitle">{{ $current ? $farm['name'] : __('farmer.my_farm_page.info') }}</h2>
                    @if ($current)
                        <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('farmer.farms.edit', $current) }}">{{ __('farms.edit') }}</a>
                    @else
                        <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('profile.edit') }}">{{ __('farmer.my_farm_page.edit_profile') }}</a>
                    @endif
                </div>
                <dl class="fmf-facts">
                    <div><dt>{{ __('farmer.my_farm_page.barangay') }}</dt><dd>{{ $farm['barangay'] ?: __('farmer.home.no_data') }}</dd></div>
                    @if ($farm['size'])
                        <div><dt>{{ __('farmer.my_farm_page.size') }}</dt><dd>{{ $farm['size']['text'] }}@if ($farm['size']['note'])<small class="d-block fad-m-small">{{ $farm['size']['note'] }}</small>@endif</dd></div>
                    @else
                        <div><dt>{{ __('farmer.my_farm_page.size') }}</dt><dd>{{ $farm['area'] ? __('farmer.my_farm_page.hectares', ['value' => number_format($farm['area'], 2)]) : __('farmer.home.no_data') }}</dd></div>
                    @endif
                    <div><dt>{{ __('farmer.my_farm_page.type') }}</dt><dd>{{ $farm['type'] ?: __('farmer.home.no_data') }}</dd></div>
                </dl>
            </section>
        @endif

        {{-- Farmer – Naicel: the Activity Calendar and "Ano ang tunay mong ani?". --}}
        @include('farmer-records._activity')

        <section class="frec-panel" aria-labelledby="fmfScheduleTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfScheduleTitle">{{ __('farmer.my_farm_page.schedule') }}</h2>
                <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('farmer.planting-schedules.create', $current ? ['farm' => $current->getKey()] : []) }}">{{ __('farmer.my_farm_page.add_schedule') }}</a>
            </div>
            @if ($schedules)
                <div class="frec-table-wrap">
                    <table class="frec-table">
                        <thead><tr>
                            <th scope="col">{{ __('farmer.my_farm_page.planted') }}</th>
                            <th scope="col">{{ app()->getLocale() === 'fil' ? 'Variety' : 'Variety' }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.harvest_due') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.status') }}</th>
                            <th scope="col" class="is-end fmf-no-print"><span class="visually-hidden">{{ __('farmer.my_farm_page.edit') }}</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($schedules as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['planting_date']) }}</th>
                                    <td data-harvest-variety>{{ $row['variety'] ?: (app()->getLocale() === 'fil' ? 'Hindi alam' : "I don't know") }}</td>
                                    <td>
                                        {{ $date($row['harvest_date']) }}
                                        @if (($row['days_left'] ?? null) !== null && $row['days_left'] >= 0)
                                            <small class="fmf-variety d-block">
                                                {{ app()->getLocale() === 'fil' ? "{$row['days_left']} araw pa" : "{$row['days_left']} days left" }}
                                            </small>
                                        @endif
                                    </td>
                                    <td>{{ $row['season'] ?? '—' }}</td>
                                    <td>{{ $row['status'] }}</td>
                                    <td class="is-end fmf-no-print"><a class="fad-btn is-quiet is-compact" href="{{ route('farmer.planting-schedules.edit', $row['id']) }}">{{ __('farmer.my_farm_page.edit') }}</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_schedule')" />
            @endif
        </section>

        @php
            $sc = $seasonComparison ?? [];
            $scFil = app()->getLocale() === 'fil';
            $scHistory = $sc['history'] ?? [];
            $scEstimate = $sc['estimate'] ?? null;
            $scMax = max(1.0, (float) ($scEstimate ?? 0), ...array_map(fn ($r) => (float) ($r['yield_t_ha'] ?? 0), $scHistory));
        @endphp
        <section class="frec-panel fmf-season-compare" id="season-comparison" aria-labelledby="fmfSeasonCompareTitle" data-my-farm-season-comparison>
            <div class="frec-panel-head is-row">
                <div>
                    <h2 id="fmfSeasonCompareTitle">{{ $scFil ? 'Paghahambing ng Ani Ngayong Panahon' : 'Season Yield Comparison' }}</h2>
                    @if (! empty($sc['season_label']))
                        <p class="fad-support mb-0">{{ $sc['season_label'] }}</p>
                    @endif
                </div>
                @if (! empty($sc['ran_at']))
                    <x-updated-at :at="$sc['ran_at']" :label="__('farmer.home.updated')" />
                @endif
            </div>

            @if (! empty($sc['summary_line']) || ! empty($scHistory) || $scEstimate !== null)
                <div class="fmf-sc-legend">
                    <span class="fmf-sc-leg-item"><span class="fmf-sc-swatch is-actual" aria-hidden="true"></span>{{ $scFil ? 'Aktuwal na ani' : 'Actual harvest' }}</span>
                    @if ($scEstimate !== null)
                        <span class="fmf-sc-leg-item"><span class="fmf-sc-swatch is-estimate" aria-hidden="true"></span>{{ $scFil ? 'Tantiya (iClimate)' : 'Estimate (iClimate)' }}</span>
                    @endif
                </div>

                @if (! empty($sc['summary_line']))
                    <p class="fw-semibold mb-1">{{ $sc['summary_line'] }}</p>
                    @if (! empty($sc['status_label']))
                        <p class="mb-2">
                            <span class="fmf-sc-status is-{{ $sc['status'] ?? 'same' }}">{{ $sc['status_label'] }}</span>
                            @if (! empty($sc['comparison_insight']))
                                <span class="small text-muted ms-2">{{ $sc['comparison_insight'] }}</span>
                            @endif
                        </p>
                    @endif
                @endif

                <dl class="fmf-sc-stats">
                    @if ($scEstimate !== null)
                        <div><dt>{{ $scFil ? 'Tantiya ngayon' : 'Current estimate' }}</dt><dd>{{ number_format((float) $scEstimate, 2) }} t/ha</dd></div>
                    @endif
                    @if (($sc['baseline'] ?? null) !== null)
                        <div><dt>{{ $scFil ? 'Karaniwan' : 'Average' }} ({{ $sc['source_label'] }})</dt><dd>{{ number_format((float) $sc['baseline'], 2) }} t/ha</dd></div>
                    @endif
                    @if (($sc['best'] ?? null) !== null)
                        <div><dt>{{ $scFil ? 'Pinakamataas' : 'Best' }}</dt><dd>{{ number_format((float) $sc['best'], 2) }} t/ha</dd></div>
                    @endif
                    @if (($sc['lowest'] ?? null) !== null)
                        <div><dt>{{ $scFil ? 'Pinakamababa' : 'Lowest' }}</dt><dd>{{ number_format((float) $sc['lowest'], 2) }} t/ha</dd></div>
                    @endif
                </dl>

                @if (! empty($scHistory) || $scEstimate !== null)
                    <div class="fmf-sc-bars" role="img" aria-label="{{ $scFil ? 'Bar chart ng mga nakaraang ani at tantiya' : 'Bar chart of past same-season harvests and current estimate' }}">
                        @foreach ($scHistory as $bar)
                            @php $pct = max(8, min(100, round(($bar['yield_t_ha'] / $scMax) * 100))); @endphp
                            <div class="fmf-sc-col">
                                <div class="fmf-sc-track">
                                    <div class="fmf-sc-bar is-actual" style="height: {{ $pct }}%"></div>
                                </div>
                                <strong class="fmf-sc-val">{{ number_format((float) $bar['yield_t_ha'], 2) }} t/ha</strong>
                                @if ($bar['tons'] !== null)
                                    <span class="fmf-sc-sub">{{ rtrim(rtrim(number_format((float) $bar['tons'], 2), '0'), '.') }} t</span>
                                @endif
                                <span class="fmf-sc-lbl">{{ $bar['label'] }}</span>
                            </div>
                        @endforeach
                        @if ($scEstimate !== null)
                            @php $estPct = max(8, min(100, round(($scEstimate / $scMax) * 100))); @endphp
                            <div class="fmf-sc-col">
                                <div class="fmf-sc-track">
                                    <div class="fmf-sc-bar is-estimate" style="height: {{ $estPct }}%"></div>
                                </div>
                                <strong class="fmf-sc-val">{{ number_format((float) $scEstimate, 2) }} t/ha</strong>
                                <span class="fmf-sc-sub">{{ $scFil ? 'Tantiya' : 'Estimate' }}</span>
                                <span class="fmf-sc-lbl">{{ $sc['season_label'] }}</span>
                            </div>
                        @endif
                    </div>
                @endif
            @endif

            @if (! empty($sc['empty_message']))
                <p class="fad-support mb-0" data-season-comparison-empty>{{ $sc['empty_message'] }}</p>
            @endif
        </section>

        <section class="frec-panel fmf-harvests" id="harvests" aria-labelledby="fmfHarvestTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfHarvestTitle">{{ __('farmer.my_farm_page.harvests') }}</h2>
            </div>
            @if ($harvests)
                <div class="frec-table-wrap">
                    {{-- Panel revision 4.3: stacks into one card per harvest on a phone (.is-stack, data-label). --}}
                    <table class="frec-table is-stack">
                        <thead><tr>
                            <th scope="col">{{ __('farmer.my_farm_page.harvested_on') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.planted') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col" class="is-num">{{ __('farmer.my_farm_page.yield') }} (t/ha)</th>
                            <th scope="col">{{ __('farmer.harvest_timing.label') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($harvests as $row)
                                <tr>
                                    <th scope="row" data-label="{{ __('farmer.my_farm_page.harvested_on') }}">{{ $date($row['harvested_on']) }}</th>
                                    <td data-label="{{ __('farmer.my_farm_page.planted') }}">{{ $date($row['planting_date']) }}</td>
                                    <td data-label="{{ __('farmer.my_farm_page.season') }}">{{ $row['season'] ?? '—' }}</td>
                                    <td class="is-num" data-label="{{ __('farmer.my_farm_page.yield') }} (t/ha)">{{ number_format($row['yield'], 2) }}</td>
                                    {{-- Panel revision 1.3 --}}
                                    <td data-label="{{ __('farmer.harvest_timing.label') }}">
                                        @if (! empty($row['timing']))
                                            <span class="timing-line is-{{ \Illuminate\Support\Str::slug($row['timing']) }}">{{ $row['timing_text'] }}</span>
                                        @else — @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_harvests')" />
            @endif
        </section>

        <section class="frec-panel fmf-no-print" aria-labelledby="fmfEventsTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfEventsTitle">{{ __('farmer.my_farm_page.events') }}</h2>
                <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index') }}">{{ __('farmer.my_farm_page.open_calendar') }}</a>
            </div>
            @if ($events)
                <ul class="fmf-events">
                    @foreach ($events as $event)
                        <li><strong>{{ $date($event['date']) }}</strong><span>{{ $event['label'] }}</span></li>
                    @endforeach
                </ul>
            @else
                <x-no-data :label="__('farmer.my_farm_page.no_events')" />
            @endif
        </section>
    </div>
</x-app-layout>
