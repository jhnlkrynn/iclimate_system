<x-app-layout>
    {{-- Calendar (2026-10, CalendarController): planning and activities only — planting dates, expected harvest dates,
         the recommended planting window and MAO activities. No advisories, weather or notifications here. Same design
         system as the Advisories page (.fad tokens in farmer-advisories.css); the calendar's own parts are .fcal-*. --}}
    @php
        $canPlan = ($plantingScheduleFeatureReady ?? true) && $isFarmer;
        $t = fn (string $key, array $replace = []) => trans('farmer.calendar_page.'.$key, $replace, $locale);
        // One name and one colour per kind of entry; the legend, the cells and Upcoming all use it.
        $kinds = ['planting' => $t('kinds.planting'), 'harvest' => $t('kinds.harvest'), 'window' => $t('kinds.window'), 'activity' => $t('kinds.activity')];
        $perDay = 2;
        $monthUrl = fn (int $month, int $year) => route('calendar.index', array_filter(['month' => $month, 'year' => $year, 'barangay' => $isFarmer ? null : $barangay]));
    @endphp

    <div class="fad fcal" lang="{{ $locale }}">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">{{ $t('kicker') }}</span>
                <h1>{{ $t('title') }}</h1>
                <p>{{ $isFarmer ? $t('intro') : 'Farmers\' planting dates and expected harvest dates, the recommended planting window and MAO activities.' }}</p>
            </div>
        </header>

        @if(session('schedule_warnings'))
            <div class="fad-empty is-watch" role="alert">
                <p class="fad-lead">Schedule saved with a date warning.</p>
                <ul class="fcal-warnings">@foreach(session('schedule_warnings') as $warning)<li>{{ $warning }}</li>@endforeach</ul>
            </div>
        @endif
        @unless($plantingScheduleFeatureReady ?? true)
            <div class="fad-empty is-watch" role="status"><p class="fad-lead">Farmer schedules are temporarily unavailable.</p><p class="fad-body">MAO activities are still shown while the database update is completed.</p></div>
        @endunless
        @if(session('schedule_feature_warning'))
            <div class="fad-empty is-watch" role="status"><p class="fad-body">{{ session('schedule_feature_warning') }}</p></div>
        @endif

        @unless ($isFarmer)
            {{-- MAO and IT see every farmer's dates; one barangay can be picked. --}}
            <form class="fcal-filter" method="GET" action="{{ route('calendar.index') }}" data-fcal-filter>
                <input type="hidden" name="month" value="{{ $current->month }}">
                <input type="hidden" name="year" value="{{ $current->year }}">
                <label class="fad-label" for="fcalBarangay">Barangay</label>
                <select class="fad-input" id="fcalBarangay" name="barangay" onchange="this.form.submit()">
                    <option value="">All barangays</option>
                    @foreach ($barangays as $name)
                        <option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>
                    @endforeach
                </select>
                <noscript><button class="fad-btn is-quiet is-compact" type="submit">Show</button></noscript>
            </form>
        @endunless

        <div class="fcal-layout">
            {{-- Month --}}
            <section class="fcal-month" aria-labelledby="fcalMonthTitle">
                <div class="fcal-toolbar">
                    <a class="fad-btn is-quiet is-compact" href="{{ $monthUrl($previousMonth, $previousYear) }}" aria-label="Previous month, {{ $current->copy()->subMonth()->format('F Y') }}"><span aria-hidden="true">‹</span> Prev</a>
                    <h2 id="fcalMonthTitle" class="fcal-month-title" aria-live="polite">{{ $current->format('F Y') }}</h2>
                    <div class="fcal-toolbar-end">
                        <a class="fad-btn is-quiet is-compact" href="{{ route('calendar.index', array_filter(['barangay' => $isFarmer ? null : $barangay])) }}" aria-label="Go to this month ({{ $today->format('F Y') }})" @if($current->isSameMonth($today)) aria-current="date" @endif>Today</a>
                        <a class="fad-btn is-quiet is-compact" href="{{ $monthUrl($nextMonth, $nextYear) }}" aria-label="Next month, {{ $current->copy()->addMonth()->format('F Y') }}">Next <span aria-hidden="true">›</span></a>
                    </div>
                </div>

                <ul class="fcal-legend" aria-label="Colour key" data-fcal-legend>
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
                                $inWindow = isset($windowDays[$day->toDateString()]);
                            @endphp
                            <div @class(['fcal-day', 'is-outside' => $outside, 'is-today' => $isToday, 'is-window' => $inWindow, 'is-empty' => count($dayEvents) === 0])
                                 role="group" aria-label="{{ $day->format('l, F j') }}{{ $isToday ? ', today' : '' }}{{ $inWindow ? ', '.$t('window_day') : '' }}{{ count($dayEvents) ? ', '.count($dayEvents).' '.(count($dayEvents) === 1 ? 'entry' : 'entries') : '' }}"
                                 @if ($inWindow) data-fcal-window="{{ $windowDays[$day->toDateString()] }}" title="{{ $t('window_day') }}" @endif>
                                <div class="fcal-day-head">
                                    <span class="fcal-day-number">{{ $day->day }}</span>
                                    <span class="fcal-day-name">{{ $day->format('D, M j') }}</span>
                                    @if($isToday)<span class="fcal-today-tag">Today</span>@endif
                                </div>
                                @foreach(array_slice($dayEvents, 0, $perDay) as $event)
                                    @include('calendar._event', ['event' => $event, 'kinds' => $kinds])
                                @endforeach
                                @if(count($dayEvents) > $perDay)
                                    {{-- "+N more" opens in place: no JavaScript, works with the keyboard. --}}
                                    <details class="fcal-more">
                                        <summary><span class="fcal-more-closed">+{{ count($dayEvents) - $perDay }} more</span><span class="fcal-more-open">Show fewer</span></summary>
                                        @foreach(array_slice($dayEvents, $perDay) as $event)
                                            @include('calendar._event', ['event' => $event, 'kinds' => $kinds])
                                        @endforeach
                                    </details>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                <p class="fcal-empty-month">{{ $t('empty_month', ['month' => $current->format('F Y')]) }}</p>
            </section>

            {{-- Upcoming --}}
            <details class="ic-collapse fcal-upcoming" open>
                <summary>
                    <span class="fcal-upcoming-title">{{ $t('upcoming') }}</span>
                    @if($upcoming->count())<span class="fad-count" aria-label="{{ $upcoming->count() }} items">{{ $upcoming->count() }}</span>@endif
                </summary>
                <div class="fcal-upcoming-body">
                    <p class="fad-support">{{ $isFarmer ? $t('upcoming_note') : 'Farmers\' planting and harvest dates, the planting window and MAO activities.' }}</p>
                    @if($upcoming->count())
                        <ol class="fcal-up-list" data-fcal-upcoming>
                            @foreach($upcoming as $event)
                                <li class="fcal-up" data-kind="{{ $event['kind'] }}">
                                    <div @class(['fcal-up-date', 'is-now' => $event['active_now']])>
                                        @if($event['active_now'])
                                            <span class="fcal-up-day">{{ $t('now') }}</span>
                                        @else
                                            <span class="fcal-up-day">{{ $event['date']->format('j') }}</span>
                                            <span class="fcal-up-month">{{ $event['date']->format('M') }}</span>
                                        @endif
                                    </div>
                                    <div class="fcal-up-body">
                                        @if ($event['url'])
                                            <a class="fcal-up-title" href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                        @else
                                            <span class="fcal-up-title">{{ $event['title'] }}</span>
                                        @endif
                                        @if($event['summary'])<p class="fcal-up-summary">{{ $event['summary'] }}</p>@endif
                                        <div class="fcal-up-meta">
                                            <span class="fad-badge fcal-kind" data-kind="{{ $event['kind'] }}">{{ $kinds[$event['kind']] }}</span>
                                            @if($event['active_now'] && $event['until'])<span class="fad-meta">{{ $t('until', ['date' => $event['until']->format('M j')]) }}</span>@endif
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <div class="fcal-up-empty">
                            <p class="fad-lead">{{ $t('upcoming_empty') }}</p>
                            <p class="fad-support">{{ $isFarmer ? $t('upcoming_empty_note') : 'Farmers\' planting plans and MAO activities appear here.' }}</p>
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
                        <a class="fad-btn is-primary fcal-add" href="{{ route('farmer.planting-schedules.create') }}" data-fast-nav-bound="true" data-plan-open aria-haspopup="dialog">@include('dashboards.mao.icon', ['name' => 'plus'])<span>Add My Planting Plan</span></a>
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
                            <form method="POST" action="{{ route('farmer.planting-schedules.destroy', $schedule) }}" data-ic-confirm="Delete this schedule?">
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
                        <a class="fad-btn is-primary fcal-add" href="{{ route('farmer.planting-schedules.create') }}" data-fast-nav-bound="true" data-plan-open aria-haspopup="dialog">@include('dashboards.mao.icon', ['name' => 'plus'])<span>Add My Planting Plan</span></a>
                    </div>
                @endforelse
            </section>
        @endif
    </div>

    @if($canPlan)
        {{-- Add My Planting Plan opens here, built from the same pop-up parts as the Advisories "View" pop-up
             (.fad-modal / .fad-m-* in farmer-advisories.css). The link still leads to the full page when scripts are
             unavailable. Outside .fad on purpose: that size container would trap a fixed-position pop-up inside it. --}}
        @php $planProfile = auth()->user()->farmerProfile; @endphp
        <div class="fad-modal" id="plantingPlanModal" data-open-on-load="{{ $errors->any() ? 'true' : 'false' }}" hidden>
            <div class="fad-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="plantingPlanModalTitle" tabindex="-1">
                <header class="fad-m-head">
                    <div class="fad-m-head-text">
                        <p class="fad-m-eyebrow">Farmer Calendar</p>
                        <h2 id="plantingPlanModalTitle" tabindex="-1">Add My Planting Plan</h2>
                        <p class="fad-m-meta"><span>Record your field plan. Unusual dates show a warning, but you can still save them.</span></p>
                    </div>
                    <button type="button" class="fad-m-x" data-plan-close aria-label="Close planting plan">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </header>
                @include('planting-schedules._form', [
                    'schedule' => new \App\Models\FarmerPlantingSchedule([
                        'barangay' => $planProfile?->barangay ?: auth()->user()->barangay,
                        'farm_type' => $planProfile?->farm_type,
                        'farm_area_hectares' => $planProfile?->farm_area,
                    ]),
                    'barangays' => \App\Support\LianBarangays::all(),
                    'action' => route('farmer.planting-schedules.store'),
                    'method' => 'POST',
                    'inModal' => true,
                ])
            </div>
        </div>
        <style>
            /* The form sits between the pop-up's header and footer, so it carries the scrolling body and the footer. */
            #plantingPlanModal form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; margin: 0; }
            #plantingPlanModal .fad-m-scroll { gap: 20px; }
            #plantingPlanModal .fcal-plan-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; align-items: start; }
            #plantingPlanModal .fad-field.is-full { grid-column: 1 / -1; }
            #plantingPlanModal .fad-input.is-invalid { border-color: var(--danger); }
            #plantingPlanModal .fcal-plan-error { font-size: .875rem; font-weight: 600; color: var(--danger-ink); }
            #plantingPlanModal .fad-m-foot { justify-content: flex-start; }
            /* The saving indicator stays in front of the pop-up. */
            html.fad-modal-open .icl-overlay { z-index: 10001; }
            @media (min-width: 641px) { #plantingPlanModal .fad-modal-dialog { width: min(640px, calc(100vw - 64px)); } }
            @media (max-width: 640px) { #plantingPlanModal .fcal-plan-grid { grid-template-columns: minmax(0, 1fr); } }
        </style>
        <script>
            // Opens and closes the same way as the Advisories pop-up (resources/js/farmer-advisories.js).
            (() => {
                const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';
                const modal = document.getElementById('plantingPlanModal');
                const dialog = modal.querySelector('.fad-modal-dialog');
                const form = modal.querySelector('[data-plan-form]');
                let opener = null;
                let touched = false;

                // The page wrapper is animated with a transform, which would make it the box a fixed pop-up covers.
                document.body.appendChild(modal);

                const open = (trigger) => {
                    opener = trigger;
                    modal.hidden = false;
                    document.documentElement.classList.add('fad-modal-open');
                    (modal.querySelector('.is-invalid') || document.getElementById('plantingPlanModalTitle')).focus();
                };
                const close = () => {
                    if (modal.hidden) return;
                    modal.hidden = true;
                    document.documentElement.classList.remove('fad-modal-open');
                    opener?.focus();
                    opener = null;
                };

                form.addEventListener('input', () => { touched = true; });

                document.addEventListener('click', (event) => {
                    const link = event.target.closest('[data-plan-open]');
                    // Ctrl/Cmd/Shift/middle click keep their normal "open in a new tab/window" meaning.
                    if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
                    event.preventDefault();
                    open(link);
                });

                modal.addEventListener('click', (event) => {
                    // A click on the dimmed page closes it only while nothing has been typed, so entries are not lost by accident.
                    if (event.target.closest('[data-plan-close]') || (event.target === modal && !touched)) close();
                });

                document.addEventListener('keydown', (event) => {
                    if (modal.hidden) return;
                    if (event.key === 'Escape') {
                        event.preventDefault();
                        close();
                        return;
                    }
                    if (event.key !== 'Tab') return;
                    // Keep keyboard focus inside the pop-up.
                    const items = Array.from(dialog.querySelectorAll(FOCUSABLE)).filter((el) => el.getClientRects().length > 0);
                    const first = items[0];
                    const last = items[items.length - 1];
                    if (event.shiftKey && (document.activeElement === first || !dialog.contains(document.activeElement))) {
                        event.preventDefault();
                        last.focus();
                    } else if (!event.shiftKey && (document.activeElement === last || !dialog.contains(document.activeElement))) {
                        event.preventDefault();
                        first.focus();
                    }
                });

                // The plan was sent back with mistakes: reopen the pop-up so they are seen.
                if (modal.dataset.openOnLoad === 'true') {
                    touched = true;
                    open(document.querySelector('[data-plan-open]'));
                }
            })();
        </script>
    @endif
</x-app-layout>
