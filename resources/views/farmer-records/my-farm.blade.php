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
            </div>
        </header>

        <section class="frec-panel" aria-labelledby="fmfInfoTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfInfoTitle">{{ __('farmer.my_farm_page.info') }}</h2>
                <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('profile.edit') }}">{{ __('farmer.my_farm_page.edit_profile') }}</a>
            </div>
            <dl class="fmf-facts">
                <div><dt>{{ __('farmer.my_farm_page.barangay') }}</dt><dd>{{ $farm['barangay'] ?: __('farmer.home.no_data') }}</dd></div>
                <div><dt>{{ __('farmer.my_farm_page.size') }}</dt><dd>{{ $farm['area'] ? __('farmer.my_farm_page.hectares', ['value' => number_format($farm['area'], 2)]) : __('farmer.home.no_data') }}</dd></div>
                <div><dt>{{ __('farmer.my_farm_page.type') }}</dt><dd>{{ $farm['type'] ?: __('farmer.home.no_data') }}</dd></div>
            </dl>
        </section>

        <section class="frec-panel" aria-labelledby="fmfScheduleTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfScheduleTitle">{{ __('farmer.my_farm_page.schedule') }}</h2>
                <a class="fad-btn is-quiet is-compact fmf-no-print" href="{{ route('farmer.planting-schedules.create') }}">{{ __('farmer.my_farm_page.add_schedule') }}</a>
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

        <section class="frec-panel fmf-harvests" aria-labelledby="fmfHarvestTitle">
            <div class="frec-panel-head is-row">
                <h2 id="fmfHarvestTitle">{{ __('farmer.my_farm_page.harvests') }}</h2>
            </div>
            @if ($harvests)
                <div class="frec-table-wrap">
                    <table class="frec-table">
                        <thead><tr>
                            <th scope="col">{{ __('farmer.my_farm_page.harvested_on') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.planted') }}</th>
                            <th scope="col">{{ __('farmer.my_farm_page.season') }}</th>
                            <th scope="col" class="is-num">{{ __('farmer.my_farm_page.yield') }} (t/ha)</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($harvests as $row)
                                <tr>
                                    <th scope="row">{{ $date($row['harvested_on']) }}</th>
                                    <td>{{ $date($row['planting_date']) }}</td>
                                    <td>{{ $row['season'] ?? '—' }}</td>
                                    <td class="is-num">{{ number_format($row['yield'], 2) }}</td>
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
