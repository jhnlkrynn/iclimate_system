<x-app-layout>
    {{-- Same design system as the farmer dashboard and Advisories page (.fad tokens, buttons and badges in
         farmer-advisories.css); the calendar's own parts are .fcal-* in farmer-calendar.css. --}}
    @php
        $isFarmer = auth()->user()->role === \App\Models\User::ROLE_FARMER;
        $canPlan = ($plantingScheduleFeatureReady ?? true) && $isFarmer;
        // One name and one colour family per kind of entry; the legend, the cells and Upcoming all use it.
        $kinds = [
            'climate' => 'Weather', 'planting' => 'Planting', 'harvesting' => 'Harvesting', 'irrigation' => 'Watering', 'community' => 'Events',
        ];
        $kindOf = fn (array $event) => match ($event['type_key']) {
            'schedule-planting' => 'planting',
            'schedule-harvest' => 'harvesting',
            default => array_key_exists($event['type_key'], $kinds) ? $event['type_key'] : 'community',
        };
        $perDay = 2;
    @endphp

    <div class="fad fcal">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Planting Schedule</span>
                <h1>Calendar</h1>
                <p>See when advisories start and expire, laid out by day, so you can plan planting and harvesting activities ahead of time.</p>
            </div>
        </header>

        @if(session('schedule_warnings'))
            <div class="fad-empty is-watch" role="alert">
                <p class="fad-lead">Schedule saved with a date warning.</p>
                <ul class="fcal-warnings">@foreach(session('schedule_warnings') as $warning)<li>{{ $warning }}</li>@endforeach</ul>
            </div>
        @endif
        @unless($plantingScheduleFeatureReady ?? true)
            <div class="fad-empty is-watch" role="status"><p class="fad-lead">Farmer schedules are temporarily unavailable.</p><p class="fad-body">Advisories and community events are still shown while the database update is completed.</p></div>
        @endunless
        @if(session('schedule_feature_warning'))
            <div class="fad-empty is-watch" role="status"><p class="fad-body">{{ session('schedule_feature_warning') }}</p></div>
        @endif

        <div class="fcal-layout">
            {{-- Month --}}
            <section class="fcal-month" aria-labelledby="fcalMonthTitle">
                <div class="fcal-toolbar">
                    <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index', ['month' => $previousMonth, 'year' => $previousYear]) }}" aria-label="Previous month, {{ $current->copy()->subMonth()->format('F Y') }}"><span aria-hidden="true">‹</span> Prev</a>
                    <h2 id="fcalMonthTitle" class="fcal-month-title" aria-live="polite">{{ $current->format('F Y') }}</h2>
                    <div class="fcal-toolbar-end">
                        <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index') }}" aria-label="Go to this month ({{ $today->format('F Y') }})" @if($current->isSameMonth($today)) aria-current="date" @endif>Today</a>
                        <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index', ['month' => $nextMonth, 'year' => $nextYear]) }}" aria-label="Next month, {{ $current->copy()->addMonth()->format('F Y') }}">Next <span aria-hidden="true">›</span></a>
                    </div>
                </div>

                <ul class="fcal-legend" aria-label="Colour key">
                    @foreach($kinds as $key => $label)
                        <li><span class="fcal-dot" data-kind="{{ $key }}" aria-hidden="true"></span>{{ $label }}</li>
                    @endforeach
                </ul>

                <div class="fcal-grid">
                    @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                        <div class="fcal-weekday" aria-hidden="true">{{ $weekday }}</div>
                    @endforeach

                    @foreach($weeks as $week)
                        @foreach($week as $day)
                            @php
                                $dayEvents = $eventsByDay[$day->toDateString()] ?? [];
                                $outside = $day->month !== $current->month;
                                $isToday = $day->isSameDay($today);
                            @endphp
                            <div @class(['fcal-day', 'is-outside' => $outside, 'is-today' => $isToday, 'is-empty' => count($dayEvents) === 0])
                                 role="group" aria-label="{{ $day->format('l, F j') }}{{ $isToday ? ', today' : '' }}{{ count($dayEvents) ? ', '.count($dayEvents).' '.(count($dayEvents) === 1 ? 'entry' : 'entries') : '' }}">
                                <div class="fcal-day-head">
                                    <span class="fcal-day-number">{{ $day->day }}</span>
                                    <span class="fcal-day-name">{{ $day->format('D, M j') }}</span>
                                    @if($isToday)<span class="fcal-today-tag">Today</span>@endif
                                </div>
                                @foreach(array_slice($dayEvents, 0, $perDay) as $event)
                                    @include('calendar._event', ['event' => $event, 'kind' => $kindOf($event), 'kinds' => $kinds])
                                @endforeach
                                @if(count($dayEvents) > $perDay)
                                    {{-- "+N more" opens in place: no JavaScript, works with the keyboard. --}}
                                    <details class="fcal-more">
                                        <summary><span class="fcal-more-closed">+{{ count($dayEvents) - $perDay }} more</span><span class="fcal-more-open">Show fewer</span></summary>
                                        @foreach(array_slice($dayEvents, $perDay) as $event)
                                            @include('calendar._event', ['event' => $event, 'kind' => $kindOf($event), 'kinds' => $kinds])
                                        @endforeach
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <p class="fcal-empty-month">Nothing is planned for {{ $current->format('F Y') }} yet.</p>
            </section>

            {{-- Upcoming --}}
            <details class="ic-collapse fcal-upcoming" open>
                <summary>
                    <span class="fcal-upcoming-title">Upcoming</span>
                    @if($upcoming->count())<span class="fad-count" aria-label="{{ $upcoming->count() }} items">{{ $upcoming->count() }}</span>@endif
                </summary>
                <div class="fcal-upcoming-body">
                    <p class="fad-support">Active and coming advisories, your planting and harvest dates, and MAO events.</p>
                    @if($upcoming->count())
                        <ol class="fcal-up-list">
                            @foreach($upcoming as $event)
                                @php $kind = $kindOf($event); @endphp
                                <li class="fcal-up">
                                    <div @class(['fcal-up-date', 'is-now' => $event['active_now']])>
                                        @if($event['active_now'])
                                            <span class="fcal-up-day">Now</span>
                                        @else
                                            <span class="fcal-up-day">{{ $event['date']->format('j') }}</span>
                                            <span class="fcal-up-month">{{ $event['date']->format('M') }}</span>
                                        @endif
                                    </div>
                                    <div class="fcal-up-body">
                                        <a class="fcal-up-title" href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                        @if($event['summary'])<p class="fcal-up-summary">{{ $event['summary'] }}</p>@endif
                                        <div class="fcal-up-meta">
                                            <span class="fad-badge fcal-kind" data-kind="{{ $kind }}">{{ $event['type_label'] }}</span>
                                            @if($event['active_now'] && $event['until'])<span class="fad-meta">Active until {{ $event['until']->format('M j') }}</span>@endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <a class="fad-link fcal-up-all" href="{{ route('planting-advisories.index') }}">See all advisories</a>
                    @else
                        <div class="fcal-up-empty">
                            <p class="fad-lead">No upcoming advisories.</p>
                            <p class="fad-support">Check back after new guidance is published.</p>
                        </div>
                    @endif
                </div>
            </details>
        </div>

        @if($canPlan)
            <section class="fcal-records" aria-labelledby="fcalRecordsTitle">
                <div class="fcal-records-head">
                    <div>
                        <h2 id="fcalRecordsTitle">My Planting and Harvest Records</h2>
                        <p class="fad-support">Edit progress or remove an incorrect record.</p>
                    </div>
                    @if($schedules->isNotEmpty())
                        <a class="fad-btn is-primary fcal-add" href="{{ route('farmer.planting-schedules.create') }}">@include('dashboards.mao.icon', ['name' => 'plus'])<span>Add My Planting Plan</span></a>
                    @endif
                </div>
                @forelse($schedules as $schedule)
                    @php $harvest = $schedule->scheduled_harvest_date ?? $schedule->harvest_month; @endphp
                    <article class="fcal-record">
                        <div class="fcal-record-main">
                            <h3 class="fcal-record-title">{{ $schedule->planting_date?->format('M j, Y') }} planting <span aria-hidden="true">→</span><span class="visually-hidden">, harvest</span> {{ $harvest?->format($schedule->scheduled_harvest_date ? 'M j, Y' : 'F Y') ?? 'harvest date not set' }}</h3>
                            <dl class="fcal-record-facts">
                                <div><dt>Season</dt><dd>{{ $schedule->season ?: '—' }}</dd></div>
                                <div><dt>Farm type</dt><dd>{{ $schedule->farm_type ?: '—' }}</dd></div>
                                <div><dt>Barangay</dt><dd>{{ $schedule->barangay ?: 'Location not provided' }}</dd></div>
                                <div><dt>Status</dt><dd>{{ $schedule->monitoringStatusLabel() }}</dd></div>
                            </dl>
                            @if($schedule->predicted_yield !== null)
                                <p class="fcal-record-estimate is-good">Estimated harvest: <strong>{{ number_format((float) $schedule->predicted_yield, 2) }} tons/hectare</strong></p>
                            @else
                                <p class="fcal-record-estimate">Estimate not available yet.@if($schedule->prediction_unavailable_reason) <span>{{ $schedule->prediction_unavailable_reason }}</span>@endif</p>
                            @endif
                        </div>
                        <div class="fcal-record-actions">
                            <a class="fad-btn is-quiet" href="{{ route('farmer.planting-schedules.edit', $schedule) }}">Edit</a>
                            <form method="POST" action="{{ route('farmer.planting-schedules.destroy', $schedule) }}" onsubmit="return confirm('Delete this schedule?')">
                                @csrf @method('DELETE')
                                <button class="fad-btn is-quiet is-danger" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    {{-- The page's one Add My Planting Plan button while there are no records (the header has none). --}}
                    <div class="fcal-records-empty">
                        <p class="fad-lead">No farm schedule yet.</p>
                        <p class="fad-body">Add your planting date and expected harvest to begin.</p>
                        <a class="fad-btn is-primary fcal-add" href="{{ route('farmer.planting-schedules.create') }}">@include('dashboards.mao.icon', ['name' => 'plus'])<span>Add My Planting Plan</span></a>
                    </div>
                @endforelse
            </section>
        @endif
    </div>
</x-app-layout>
