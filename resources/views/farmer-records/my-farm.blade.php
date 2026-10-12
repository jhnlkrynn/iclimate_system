<x-app-layout>
    {{-- "Aking Bukid" (FarmerMyFarmController): the farmer's own farm, planting schedule, harvest records (printable)
         and upcoming dates. Same look as the other farmer pages (.fad / .frec). --}}
    @php
        $date = fn ($value) => $value ? \Illuminate\Support\Carbon::parse($value)->locale(app()->getLocale())->translatedFormat('M j, Y') : '—';
    @endphp

    <div class="fad frec fmf" data-my-farm>
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
                <button type="button" class="fad-btn is-light" onclick="window.print()">{{ __('farmer.my_farm_page.print') }}</button>
                {{-- Manuscript – Naicel (2026-10-08): My Reports (prediction and advisory summaries, PDF). --}}
                @if (\Illuminate\Support\Facades\Route::has('farmer.reports.index'))
                    <a class="fad-btn is-light" href="{{ route('farmer.reports.index') }}" data-my-reports-link>{{ __('my_reports.title') }}</a>
                @endif
            </div>
        </header>

        @if (session('success'))
            <div class="frec-note fmf-no-print" role="status">{{ session('success') }}</div>
        @endif

        @if ($available)
            {{-- My Farms: every farm of this farmer, each with its own location, size and map. --}}
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
                                    </div>
                                </div>
                                <p class="ffm-card-place">{{ $f['barangay'] ? __('farms.location_line', ['barangay' => $f['barangay']]) : __('farms.no_barangay') }}@if ($f['location']) · {{ $f['location'] }}@endif</p>
                                <p class="ffm-card-size">{{ $f['size']['text'] }}@if ($f['size']['note'])<small>{{ $f['size']['note'] }}</small>@endif</p>
                                <p class="ffm-card-map"><span class="fad-badge {{ $f['mapped'] ? 'is-good' : 'is-muted' }}">{{ $f['mapped'] ? __('farms.mapped') : __('farms.not_mapped') }}</span></p>
                                <div class="ffm-card-actions">
                                    @unless ($f['current'])
                                        <form method="POST" action="{{ route('farmer.farms.select') }}">
                                            @csrf
                                            <input type="hidden" name="farm_id" value="{{ $f['id'] }}">
                                            <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farms.view') }}</button>
                                        </form>
                                    @endunless
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('farmer.farms.edit', $f['id']) }}">{{ __('farms.edit') }}</a>
                                    <a class="fad-btn is-quiet is-compact" href="{{ route('farmer.farms.map', $f['id']) }}">{{ __('farms.map_action') }}</a>
                                    @unless ($f['primary'])
                                        <form method="POST" action="{{ route('farmer.farms.primary', $f['id']) }}">
                                            @csrf
                                            <button type="submit" class="fad-btn is-quiet is-compact">{{ __('farms.set_primary') }}</button>
                                        </form>
                                    @endunless
                                    <form method="POST" action="{{ route('farmer.farms.destroy', $f['id']) }}"
                                        data-ic-confirm="{{ count($farms) === 1 ? __('farms.remove_only_confirm', ['name' => $f['name']]) : __('farms.remove_confirm', ['name' => $f['name']]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="fad-btn is-quiet is-compact ffm-remove">{{ __('farms.remove') }}</button>
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
                            <th scope="col">{{ __('farmer.my_farm_page.harvest_due') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.status') }}</th>
                            <th scope="col" class="is-end fmf-no-print"><span class="visually-hidden">{{ __('farmer.my_farm_page.edit') }}</span></th>
                        </tr></thead>
                        <tbody>
                            @foreach ($schedules as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['planting_date']) }}</th>
                                    <td>{{ $date($row['harvest_date']) }}</td>
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
