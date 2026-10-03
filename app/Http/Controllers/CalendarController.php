<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class CalendarController extends Controller
{
    /**
     * Private to the person (advisories for their barangay, their own schedules) and reused for a minute
     * per month shown; advisory, post and schedule changes rebuild it. Five database round trips to build.
     */
    public function index(Request $request): View
    {
        return view('calendar.index', \App\Support\PageCache::forUser(
            (int) $request->user()->id,
            'calendar',
            60,
            fn (): array => $this->calendarData($request),
            // Keyed by the Lian day, so "today" and Upcoming roll over at midnight Philippine time.
            \App\Support\PageCache::queryVary($request, ['month', 'year']) + ['day' => BusinessClock::today()->toDateString()],
        ));
    }

    /**
     * Days on this page are Lian (Asia/Manila) days. Advisory and post times are stored in UTC, so they are
     * converted before they are placed on a day; planting dates are plain dates and need no conversion.
     *
     * @return array<string, mixed>
     */
    private function calendarData(Request $request): array
    {
        $now = BusinessClock::now();
        $month = max(1, min(12, (int) $request->integer('month', $now->month)));
        $year = (int) $request->integer('year', $now->year);

        $current = Carbon::create($year, $month, 1)->startOfMonth();
        $gridStart = $current->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $current->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $today = Carbon::parse(BusinessClock::today()->toDateString())->startOfDay();
        $user = $request->user();

        // A day either side so advisories that cross midnight in Lian time are not missed; the loop below clamps.
        $advisoryRuns = $this->advisoryRuns($user, $gridStart->copy()->subDay(), $gridEnd->copy()->addDay());

        $feedEvents = FeedPost::query()
            ->whereNull('archived_at')
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$gridStart->copy()->subDay(), $gridEnd->copy()->addDays(2)])
            ->orderBy('event_date')
            ->get()
            ->filter(fn (FeedPost $post) => $this->lianDay($post->event_date)->between($gridStart, $gridEnd));

        $plantingScheduleFeatureReady = \App\Support\SchemaCache::hasTable('farmer_planting_schedules');
        $schedules = $plantingScheduleFeatureReady
            ? FarmerPlantingSchedule::query()
                ->with('farmer:id,name')
                ->when($user->role === User::ROLE_FARMER, fn ($query) => $query->where('user_id', $user->id))
                ->where(function ($query) use ($gridStart, $gridEnd) {
                    $query->whereBetween('planting_date', [$gridStart, $gridEnd])
                        ->orWhereBetween('scheduled_harvest_date', [$gridStart, $gridEnd])
                        ->orWhereBetween('harvest_month', [$gridStart->copy()->startOfMonth(), $gridEnd->copy()->endOfMonth()]);
                })
                ->orderBy('planting_date')
                ->get()
            : collect();

        // An advisory is shown on every day it is valid (that is the point of the page: see when advice starts and ends).
        $eventsByDay = [];
        foreach ($advisoryRuns as $run) {
            $start = $run['start']->lt($gridStart) ? $gridStart->copy() : $run['start']->copy();
            $end = $run['end']->gt($gridEnd) ? $gridEnd->copy() : $run['end']->copy();

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $eventsByDay[$day->toDateString()][] = $run['event'];
            }
        }
        // PAGASA is checked several times a day and each check stores its own advisory, often with the same title and
        // text. Those stay in the table as fetch history; a day shows each identical advisory once.
        $eventsByDay = array_map(fn (array $events) => collect($events)->unique(fn (array $event) => $event['title'].'|'.$event['summary'])->values()->all(), $eventsByDay);

        foreach ($feedEvents as $post) {
            $eventsByDay[$this->lianDay($post->event_date)->toDateString()][] = $this->feedCalendarEvent($post);
        }

        foreach ($schedules as $schedule) {
            if ($schedule->planting_date->between($gridStart, $gridEnd)) {
                $eventsByDay[$schedule->planting_date->toDateString()][] = $this->scheduleCalendarEvent($schedule, false);
            }

            $harvest = $schedule->scheduled_harvest_date ?? $schedule->harvest_month;
            if ($harvest?->between($gridStart, $gridEnd)) {
                $eventsByDay[$harvest->toDateString()][] = $this->scheduleCalendarEvent($schedule, true);
            }
        }

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $cursor->copy();
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        $previous = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();

        return [
            'current' => $current,
            'today' => $today,
            'weeks' => $weeks,
            'eventsByDay' => $eventsByDay,
            'upcoming' => $this->upcoming($user, $today, $plantingScheduleFeatureReady),
            'previousMonth' => $previous->month,
            'previousYear' => $previous->year,
            'nextMonth' => $next->month,
            'nextYear' => $next->year,
            'schedules' => $plantingScheduleFeatureReady && $user->role === User::ROLE_FARMER
                ? $user->plantingSchedules()->latest('planting_date')->get()
                : collect(),
            'plantingScheduleFeatureReady' => $plantingScheduleFeatureReady,
        ];
    }

    /**
     * The advisories that touch [$from, $to], each with the Lian days it covers.
     *
     * Every forecast run of a rule creates its own advisory with the same title and a long validity (a 16-day
     * outlook stays valid for 30 days), so runs overlap and the same advice appeared twice on every day.
     * A run now ends the day before the next run of the same rule, title and target starts; the newest run
     * keeps its own end. Earlier days still show the run that was current then. Nothing in the table changes.
     *
     * @return Collection<int, array{advisory: PlantingAdvisory, start: Carbon, end: Carbon, event: array<string, mixed>}>
     */
    private function advisoryRuns(User $user, Carbon $from, Carbon $to): Collection
    {
        $advisories = PlantingAdvisory::query()
            ->when(
                $user->role === User::ROLE_FARMER,
                fn ($query) => $query->published()->forBarangay($user->barangay),
                fn ($query) => $query->whereIn('status', [PlantingAdvisory::STATUS_PUBLISHED, PlantingAdvisory::STATUS_PENDING_REVIEW])
            )
            ->where(fn ($query) => $query->whereNull('valid_from')->orWhere('valid_from', '<=', $to->copy()->endOfDay()))
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhere('valid_until', '>=', $from))
            ->orderBy('valid_from')
            ->get();

        return $advisories
            ->map(function (PlantingAdvisory $advisory) use ($from) {
                $start = $advisory->valid_from ? $this->lianDay($advisory->valid_from) : $from->copy();

                return [
                    'advisory' => $advisory,
                    'start' => $start,
                    'end' => $advisory->valid_until ? $this->lianDay($advisory->valid_until) : $start->copy(),
                    'event' => $this->advisoryCalendarEvent($advisory),
                ];
            })
            ->groupBy(fn (array $run) => $run['advisory']->advisory_rule_id
                ? implode('|', [$run['advisory']->advisory_rule_id, $run['advisory']->title, (string) $run['advisory']->target_barangay])
                : 'single-'.$run['advisory']->id)
            ->flatMap(function (Collection $group) {
                $group = $group->sortBy(fn (array $run) => [$run['start']->timestamp, $run['advisory']->created_at?->timestamp])->values();

                return $group->map(function (array $run, int $i) use ($group) {
                    $nextRun = $group->get($i + 1);
                    if ($nextRun && $nextRun['start']->copy()->subDay()->lt($run['end'])) {
                        $run['end'] = $nextRun['start']->copy()->subDay();
                    }

                    return $run;
                });
            })
            ->filter(fn (array $run) => $run['end']->gte($run['start']))
            ->values();
    }

    /**
     * What is coming up from today, whatever month is shown: advisories that are active now or start later,
     * community events and the farmer's own planting and harvest dates, in date order.
     */
    private function upcoming(User $user, Carbon $today, bool $schedulesReady): Collection
    {
        $advisories = $this->advisoryRuns($user, $today, $today->copy()->addYears(2))
            ->filter(fn (array $run) => $run['end']->gte($today))
            ->map(fn (array $run) => $run['event'] + [
                'date' => $run['start']->gt($today) ? $run['start'] : $today->copy(),
                'active_now' => $run['start']->lte($today),
                'until' => $run['advisory']->valid_until ? $run['end'] : null,
            ]);

        $feed = FeedPost::query()
            ->whereNull('archived_at')
            ->where('event_date', '>=', $today->copy()->subDay())
            ->orderBy('event_date')
            ->limit(20)
            ->get()
            ->filter(fn (FeedPost $post) => $this->lianDay($post->event_date)->gte($today))
            ->map(fn (FeedPost $post) => $this->feedCalendarEvent($post) + ['date' => $this->lianDay($post->event_date), 'active_now' => false, 'until' => null]);

        $schedules = $schedulesReady
            ? FarmerPlantingSchedule::query()
                ->when($user->role === User::ROLE_FARMER, fn ($query) => $query->where('user_id', $user->id))
                ->where(fn ($query) => $query->where('planting_date', '>=', $today)->orWhere('scheduled_harvest_date', '>=', $today)->orWhere('harvest_month', '>=', $today->copy()->startOfMonth()))
                ->get()
                ->flatMap(function (FarmerPlantingSchedule $schedule) use ($today) {
                    $events = [];
                    if ($schedule->planting_date->gte($today)) {
                        $events[] = $this->scheduleCalendarEvent($schedule, false) + ['date' => $schedule->planting_date, 'active_now' => false, 'until' => null];
                    }
                    $harvest = $schedule->scheduled_harvest_date ?? $schedule->harvest_month;
                    if ($harvest?->gte($today)) {
                        $events[] = $this->scheduleCalendarEvent($schedule, true) + ['date' => $harvest, 'active_now' => false, 'until' => null];
                    }

                    return $events;
                })
            : collect();

        return $advisories->concat($feed)->concat($schedules)
            ->unique(fn (array $event) => $event['date']->toDateString().'|'.$event['title'].'|'.$event['summary'])
            ->sortBy(fn (array $event) => [$event['date']->toDateString(), $event['active_now'] ? 0 : 1, $event['until']?->toDateString() ?? '9999', $event['title']])  // active ones ending soonest first
            ->values()
            ->take(8);
    }

    /** The Lian (Philippine) calendar day of a stored UTC time. */
    private function lianDay($time): Carbon
    {
        return Carbon::parse($time->copy()->timezone(BusinessClock::timezone())->toDateString())->startOfDay();
    }

    private function advisoryCalendarEvent(PlantingAdvisory $advisory): array
    {
        return [
            'title' => $advisory->title,
            'summary' => $advisory->displaySummary(),   // never the raw PAGASA page text
            'url' => route('planting-advisories.show', $advisory),
            'starts_at' => $advisory->valid_from,
            'type_key' => $advisory->advisory_type ?: $advisory->type,
            'type_label' => $advisory->typeLabel(),
            'badge_class' => $advisory->typeBadgeClass(),
        ];
    }

    private function feedCalendarEvent(FeedPost $post): array
    {
        return [
            'title' => $post->title,
            'summary' => str($post->body)->limit(130)->toString(),
            'url' => route('community-feed.index').'#post-'.$post->id,
            'starts_at' => $post->event_date,
            'type_key' => 'community',
            'type_label' => 'Event',
            'badge_class' => 'text-bg-light',
        ];
    }

    private function scheduleCalendarEvent(FarmerPlantingSchedule $schedule, bool $harvest): array
    {
        $date = $harvest ? ($schedule->scheduled_harvest_date ?? $schedule->harvest_month) : $schedule->planting_date;

        return [
            'title' => ($harvest ? 'Harvest' : 'Planting').' - '.($schedule->barangay ?: 'Farm schedule'),
            'summary' => $schedule->season.' season, '.$schedule->farm_type.' - '.$schedule->monitoringStatusLabel(),
            'url' => auth()->user()?->role === User::ROLE_FARMER
                ? route('farmer.planting-schedules.edit', $schedule)
                : route('calendar.index', ['month' => $date?->month, 'year' => $date?->year]),
            'starts_at' => $date,
            'type_key' => $harvest ? 'schedule-harvest' : 'schedule-planting',
            'type_label' => auth()->user()?->role === User::ROLE_FARMER
                ? ($harvest ? 'My Harvest' : 'My Planting')
                : ($harvest ? 'Farmer Harvest' : 'Farmer Planting'),
            'badge_class' => $harvest ? 'text-bg-warning' : 'text-bg-success',
        ];
    }
}
