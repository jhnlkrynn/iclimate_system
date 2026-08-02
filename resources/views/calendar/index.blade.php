<x-app-layout>
    @include('layouts.partials.dark-workspace')
    <div class="dark-workspace calendar-page">
        <style>
            .calendar-page .calendar-card, .calendar-page .upcoming-card { overflow: hidden; border-color: var(--ic-sand-dark); box-shadow: 0 1.2rem 2.6rem rgba(13,31,24,.08); }
            .calendar-page .calendar-toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 1rem; padding: 1rem 1.25rem; border-bottom: 1px solid var(--ic-border); background: linear-gradient(90deg, rgba(82,183,136,.08), rgba(31,42,36,.02)); }
            .calendar-page .calendar-toolbar-month { font-family: 'DM Serif Display', serif; font-size: 1.45rem; min-width: 170px; text-align: center; color: var(--ic-ink); }
            .calendar-page .calendar-legend { display: flex; flex-wrap: wrap; gap: .6rem; padding: 1rem 1.25rem 0; }
            .calendar-page .calendar-legend-item { display: inline-flex; align-items: center; gap: .4rem; font-size: .75rem; color: var(--ic-ink-mid); }
            .calendar-page .calendar-legend-dot { width: 10px; height: 10px; border-radius: 999px; box-shadow: 0 0 0 4px rgba(31,42,36,.05); }
            .calendar-page .calendar-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); padding: .75rem; gap: .45rem; }
            .calendar-page .calendar-weekday { padding: .6rem .5rem; font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--ic-muted); text-align: center; }
            .calendar-page .calendar-day { min-height: 128px; padding: .55rem; border: 1px solid var(--ic-border); border-radius: 10px; background: rgba(31,42,36,.02); transition: transform .15s ease, border-color .15s ease, background .15s ease; }
            .calendar-page .calendar-day:hover { transform: translateY(-1px); border-color: rgba(116,198,157,.4); background: rgba(82,183,136,.06); }
            .calendar-page .calendar-day.is-outside { opacity: .5; background: rgba(31,42,36,.01); }
            .calendar-page .calendar-day.is-today { border-color: rgba(82,183,136,.62); background: linear-gradient(145deg, rgba(82,183,136,.16), rgba(82,183,136,.045)); box-shadow: inset 0 0 0 1px rgba(82,183,136,.18); }
            .calendar-page .calendar-day-number { display: inline-grid; place-items: center; min-width: 1.75rem; height: 1.75rem; padding: 0 .35rem; border-radius: 999px; font-size: .78rem; color: var(--ic-ink-mid); margin-bottom: .45rem; font-weight: 800; }
            .calendar-page .calendar-day.is-today .calendar-day-number { color: #0d1f18; background: var(--ic-green-400); }
            .calendar-page .calendar-event { display: grid; gap: .25rem; font-size: .68rem; line-height: 1.25; padding: .42rem .5rem; border-radius: 8px; margin-bottom: .35rem; text-decoration: none; color: var(--ic-ink) !important; background: rgba(31,42,36,.035); border: 1px solid var(--ic-border); border-left: 4px solid var(--event-accent, var(--ic-green-500)); }
            .calendar-page .calendar-event:hover { background: rgba(31,42,36,.06); border-color: rgba(31,42,36,.14); transform: translateX(1px); }
            .calendar-page .calendar-event-title { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; font-weight: 800; }
            .calendar-page .calendar-event-badge { justify-self: start; border-radius: 999px; padding: .15rem .42rem; font-family: 'DM Mono', monospace; font-size: .58rem; font-weight: 800; letter-spacing: .04em; color: #fff; background: var(--event-accent, var(--ic-green-500)); }
            .calendar-page .calendar-event.type-climate { --event-accent: #0d6efd; }
            .calendar-page .calendar-event.type-planting { --event-accent: #198754; }
            .calendar-page .calendar-event.type-harvesting { --event-accent: #e8a73d; }
            .calendar-page .calendar-event.type-irrigation { --event-accent: #0dcaf0; }
            .calendar-page .calendar-event.type-community { --event-accent: #b7e4c7; }
            .calendar-page .calendar-event-more { font-size: .68rem; color: var(--ic-muted); padding-left: .25rem; }
            .calendar-page .upcoming-item { display: flex; gap: .75rem; padding: .75rem 0; border-bottom: 1px solid var(--ic-border); }
            .calendar-page .upcoming-item:hover { background: rgba(82,183,136,.06); margin-inline: -.55rem; padding-inline: .55rem; border-radius: 10px; }
            .calendar-page .upcoming-item:last-child { border-bottom: none; }
            .calendar-page .upcoming-date { min-width: 54px; text-align: center; padding-top: .15rem; }
            .calendar-page .upcoming-date-day { font-family: 'DM Serif Display', serif; font-size: 1.35rem; line-height: 1; color: var(--ic-ink); }
            .calendar-page .upcoming-date-month { font-size: .65rem; text-transform: uppercase; letter-spacing: .06em; color: var(--ic-muted); }
            .calendar-page .upcoming-title { color: var(--ic-ink); line-height: 1.25; }
            .calendar-page .upcoming-title:hover { color: var(--ic-green-700); }
            @media (max-width: 767.98px) {
                .calendar-page .calendar-grid { grid-template-columns: 1fr; }
                .calendar-page .calendar-weekday { display: none; }
                .calendar-page .calendar-day { min-height: auto; }
                .calendar-page .calendar-day.is-outside { display: none; }
            }
        </style>

        <section class="page-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3 align-items-xl-end">
                <div>
                    <div class="eyebrow mb-2">Planting Schedule</div>
                    <h1 class="h2 fw-bold mb-2">Calendar</h1>
                    <p class="mb-0" style="color: var(--ic-ink-mid);">See when advisories start and expire, laid out by day, so you can plan planting and harvesting activities ahead of time.</p>
                </div>
            </div>
        </section>

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card no-lift calendar-card">
                    <div class="calendar-toolbar">
                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $previousMonth, 'year' => $previousYear]) }}" aria-label="Previous month">&laquo; Prev</a>
                        <div class="calendar-toolbar-month">{{ $current->format('F Y') }}</div>
                        <div class="d-flex gap-2">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('calendar.index') }}">Today</a>
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('calendar.index', ['month' => $nextMonth, 'year' => $nextYear]) }}" aria-label="Next month">Next &raquo;</a>
                        </div>
                    </div>

                    <div class="calendar-legend">
                        <span class="calendar-legend-item"><span class="calendar-legend-dot bg-primary"></span> Climate</span>
                        <span class="calendar-legend-item"><span class="calendar-legend-dot bg-success"></span> Planting</span>
                        <span class="calendar-legend-item"><span class="calendar-legend-dot bg-warning"></span> Harvesting</span>
                        <span class="calendar-legend-item"><span class="calendar-legend-dot bg-info"></span> Irrigation</span>
                        <span class="calendar-legend-item"><span class="calendar-legend-dot" style="background:#b7e4c7;"></span> Events</span>
                    </div>

                    <div class="calendar-grid">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                            <div class="calendar-weekday">{{ $weekday }}</div>
                        @endforeach

                        @foreach($weeks as $week)
                            @foreach($week as $day)
                                @php
                                    $dayEvents = $eventsByDay[$day->toDateString()] ?? [];
                                    $visibleEvents = collect($dayEvents)->take(2);
                                    $remaining = count($dayEvents) - $visibleEvents->count();
                                @endphp
                                <div class="calendar-day {{ $day->month !== $current->month ? 'is-outside' : '' }} {{ $day->isSameDay($today) ? 'is-today' : '' }}">
                                    <div class="calendar-day-number">{{ $day->day }}</div>
                                    @foreach($visibleEvents as $event)
                                        <a href="{{ $event['url'] }}" class="calendar-event type-{{ $event['type_key'] }}" title="{{ $event['title'] }}">
                                            <span class="calendar-event-title">{{ $event['title'] }}</span>
                                            <span class="calendar-event-badge">{{ $event['type_label'] }}</span>
                                        </a>
                                    @endforeach
                                    @if($remaining > 0)
                                        <div class="calendar-event-more">+{{ $remaining }} more</div>
                                    @endif
                                </div>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card no-lift h-100 upcoming-card">
                    <div class="card-header border-0 py-3">
                        <div class="fw-semibold">Upcoming</div>
                        <div class="small text-muted">Advisories and important MAO events.</div>
                    </div>
                    <div class="card-body pt-0">
                        @if($upcoming->count())
                            @foreach($upcoming as $event)
                                <div class="upcoming-item">
                                    <div class="upcoming-date">
                                        <div class="upcoming-date-day">{{ $event['starts_at']?->format('d') ?? '--' }}</div>
                                        <div class="upcoming-date-month">{{ $event['starts_at']?->format('M') ?? '' }}</div>
                                    </div>
                                    <div>
                                        <a class="fw-semibold text-decoration-none upcoming-title" href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                        <div class="small text-muted">{{ $event['summary'] }}</div>
                                        <span class="badge {{ $event['badge_class'] }} mt-1">{{ $event['type_label'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="empty-state text-center p-4">
                                <div class="mb-1">No upcoming advisories.</div>
                                <p class="text-muted mb-0 small">Check back after new guidance is published.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
