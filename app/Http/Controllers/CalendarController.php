<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Support\BusinessClock;
use App\Support\FarmerLanguage;
use App\Support\LianBarangays;
use App\Support\PageCache;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Calendar (2026-10): planning and activities only — planting dates, expected harvest dates, the recommended planting
 * window and MAO activities. Advisories, weather and notifications are not shown here (advisories are a list on their
 * own page). A farmer sees only their own dates and the MAO activities for their barangay; MAO and IT see every
 * farmer's dates and can pick one barangay.
 */
class CalendarController extends Controller
{
    /** How many entries the Upcoming list shows. */
    private const UPCOMING_LIMIT = 8;

    /**
     * Private to the person and reused for a minute per month shown; schedule, advisory and post changes rebuild it.
     */
    public function index(Request $request): View
    {
        return view('calendar.index', PageCache::forUser(
            (int) $request->user()->id,
            'calendar',
            60,
            fn (): array => $this->calendarData($request),
            // Keyed by the Lian day, so "today" and Upcoming roll over at midnight Philippine time.
            PageCache::queryVary($request, ['month', 'year', 'barangay']) + ['day' => BusinessClock::today()->toDateString(), 'locale' => FarmerLanguage::resolve($request)],
        ));
    }

    /**
     * Days on this page are Lian (Asia/Manila) days. Post times are stored in UTC, so they are converted before they
     * are placed on a day; planting, harvest and activity dates are plain dates and need no conversion.
     *
     * @return array<string, mixed>
     */
    private function calendarData(Request $request): array
    {
        $now = BusinessClock::now();
        $month = max(1, min(12, (int) $request->integer('month', $now->month)));
        $rawYear = (int) $request->integer('year', $now->year);
        $year = ($rawYear >= 1970 && $rawYear <= 2100) ? $rawYear : $now->year;

        $current = Carbon::create($year, $month, 1)->startOfMonth();
        $gridStart = $current->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $current->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $today = Carbon::parse(BusinessClock::today()->toDateString())->startOfDay();
        $user = $request->user();
        $isFarmer = $user->role === User::ROLE_FARMER;
        $locale = $isFarmer ? FarmerLanguage::resolve($request) : 'en';
        // A farmer's calendar is always their own; staff may narrow everything to one barangay.
        $barangay = $isFarmer
            ? LianBarangays::canonicalName($user->registeredBarangay())
            : LianBarangays::canonicalName(is_string($request->query('barangay')) ? $request->query('barangay') : null);
        $schedulesReady = SchemaCache::hasTable('farmer_planting_schedules');

        $eventsByDay = [];
        foreach ($this->scheduleEvents($user, $isFarmer ? null : $barangay, $schedulesReady, $locale) as $event) {
            if ($event['date']->between($gridStart, $gridEnd)) {
                $eventsByDay[$event['date']->toDateString()][] = $event;
            }
        }
        foreach ($this->activityEvents($isFarmer, $barangay) as $event) {
            if ($event['date']->between($gridStart, $gridEnd)) {
                $eventsByDay[$event['date']->toDateString()][] = $event;
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
        $windows = $this->plantingWindows($gridStart, $gridEnd);

        return [
            'current' => $current,
            'today' => $today,
            'weeks' => $weeks,
            'eventsByDay' => $eventsByDay,
            // The days of the shown month that fall inside a recommended planting window.
            'windowDays' => $this->windowDays($windows, $gridStart, $gridEnd),
            'upcoming' => $this->upcoming($user, $isFarmer, $barangay, $today, $schedulesReady, $locale),
            'previousMonth' => $previous->month,
            'previousYear' => $previous->year,
            'nextMonth' => $next->month,
            'nextYear' => $next->year,
            // The plans of the farm the farmer is viewing (older plans without a farm count as the primary farm's).
            'schedules' => $schedulesReady && $isFarmer ? \App\Models\FarmerPlantingSchedule::withVarieties($user->plantingSchedules()
                ->when(($calendarFarm = \App\Support\ActiveFarm::current($user)) !== null, fn ($q) => $q->where(fn ($w) => $w->where('farm_id', $calendarFarm->getKey())
                    ->when($calendarFarm->is_primary, fn ($p) => $p->orWhereNull('farm_id'))))
                ->latest('planting_date')->get()) : collect(),
            'plantingScheduleFeatureReady' => $schedulesReady,
            'isFarmer' => $isFarmer,
            'locale' => $locale,
            'barangay' => $barangay,
            'barangays' => LianBarangays::all(),
        ];
    }

    /**
     * Planting dates and expected harvest dates: the farmer's own, or (staff) every farmer's, optionally one barangay.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function scheduleEvents(User $user, ?string $barangay, bool $ready, string $locale): Collection
    {
        if (! $ready) {
            return collect();
        }
        $isFarmer = $user->role === User::ROLE_FARMER;

        return FarmerPlantingSchedule::query()
            ->with('farmer:id,name')
            ->when($isFarmer, fn ($query) => $query->where('user_id', $user->id))
            ->orderBy('planting_date')
            ->get()
            ->filter(fn (FarmerPlantingSchedule $s): bool => $barangay === null || LianBarangays::canonicalName($s->barangay) === $barangay)
            ->flatMap(function (FarmerPlantingSchedule $schedule) use ($isFarmer, $locale): array {
                $events = [];
                foreach ([false, true] as $harvest) {
                    $date = $harvest ? ($schedule->scheduled_harvest_date ?? $schedule->harvest_month) : $schedule->planting_date;
                    if (! $date) {
                        continue;
                    }
                    $what = $harvest ? 'Expected harvest' : 'Planting';
                    $events[] = [
                        'date' => Carbon::parse($date->toDateString())->startOfDay(),
                        'title' => $isFarmer
                            ? trans('farmer.calendar_page.'.($harvest ? 'my_harvest' : 'my_planting'), [], $locale)
                            : $what.' – '.($schedule->farmer?->name ?: 'Farmer'),
                        'summary' => trim(($schedule->barangay ? $schedule->barangay.' · ' : '').$schedule->season.' season, '.$schedule->farm_type),
                        'url' => $isFarmer
                            ? route('farmer.planting-schedules.edit', $schedule)
                            : route('calendar.index', ['month' => $date->month, 'year' => $date->year]),
                        'kind' => $harvest ? 'harvest' : 'planting',
                    ];
                }

                return $events;
            })
            ->values();
    }

    /**
     * MAO activities: the optional "MAO Activity" of a published advisory, and MAO posts that carry an event date.
     * A farmer gets the ones for their barangay and for all of Lian.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function activityEvents(bool $isFarmer, ?string $barangay): Collection
    {
        $fromAdvisories = PlantingAdvisory::query()
            ->published()
            ->where('metadata', 'like', '%mao_activity_date%')
            ->when($isFarmer || $barangay, fn ($query) => $query->where(fn ($q) => $q->whereNull('target_barangay')->orWhere('target_barangay', '')->orWhere('target_barangay', $barangay)))
            ->get()
            ->map(function (PlantingAdvisory $advisory): ?array {
                $date = rescue(fn () => Carbon::parse((string) data_get($advisory->metadata, 'mao_activity_date'))->startOfDay(), null, false);
                $activity = trim((string) data_get($advisory->metadata, 'mao_activity'));

                return $date && $activity !== '' ? [
                    'date' => $date,
                    'title' => $activity,
                    'summary' => $advisory->target_barangay ?: 'All of Lian',
                    'url' => route('planting-advisories.show', $advisory),
                    'kind' => 'activity',
                ] : null;
            })
            ->filter();

        $fromPosts = FeedPost::query()
            ->whereNull('archived_at')
            ->whereNotNull('event_date')
            ->orderBy('event_date')
            ->get()
            ->map(fn (FeedPost $post): array => [
                'date' => $this->lianDay($post->event_date),
                'title' => (string) $post->title,
                'summary' => str($post->body)->limit(130)->toString(),
                'url' => $isFarmer ? route('planting-advisories.index').'#announcements' : route('community-feed.index').'#post-'.$post->id,
                'kind' => 'activity',
            ]);

        return $fromAdvisories->concat($fromPosts)->values();
    }

    /**
     * The recommended planting windows (config/farm_calendar.php) that touch [$from, $to].
     *
     * @return array<int, array{season: string, start: Carbon, end: Carbon}>
     */
    private function plantingWindows(Carbon $from, Carbon $to): array
    {
        $windows = [];
        foreach (range($from->year - 1, $to->year) as $year) {
            foreach ((array) config('farm_calendar.planting_windows', []) as $season => $range) {
                $start = rescue(fn () => Carbon::parse($year.'-'.$range['from'])->startOfDay(), null, false);
                $end = rescue(fn () => Carbon::parse($year.'-'.$range['until'])->startOfDay(), null, false);
                if (! $start || ! $end) {
                    continue;
                }
                if ($end->lt($start)) {
                    $end->addYear();   // a window that crosses the new year
                }
                if ($end->gte($from) && $start->lte($to)) {
                    $windows[] = ['season' => (string) $season, 'start' => $start, 'end' => $end];
                }
            }
        }
        usort($windows, fn (array $a, array $b): int => $a['start'] <=> $b['start']);

        return $windows;
    }

    /** @return array<string, string> date => season, for every day of [$from, $to] inside a window */
    private function windowDays(array $windows, Carbon $from, Carbon $to): array
    {
        $days = [];
        foreach ($windows as $window) {
            $day = $window['start']->gt($from) ? $window['start']->copy() : $from->copy();
            $last = $window['end']->lt($to) ? $window['end'] : $to;
            for (; $day->lte($last); $day->addDay()) {
                $days[$day->toDateString()] = $window['season'];
            }
        }

        return $days;
    }

    /** What is coming up from today, whatever month is shown, in date order. */
    private function upcoming(User $user, bool $isFarmer, ?string $barangay, Carbon $today, bool $schedulesReady, string $locale): Collection
    {
        $events = $this->scheduleEvents($user, $isFarmer ? null : $barangay, $schedulesReady, $locale)
            ->concat($this->activityEvents($isFarmer, $barangay))
            ->filter(fn (array $event): bool => $event['date']->gte($today))
            ->map(fn (array $event): array => $event + ['active_now' => false, 'until' => null]);

        // The window that is open now, or else the next one to open.
        $window = collect($this->plantingWindows($today, $today->copy()->addYear()))->first(fn (array $w): bool => $w['end']->gte($today));
        if ($window) {
            $events->push([
                'date' => $window['start']->gt($today) ? $window['start'] : $today->copy(),
                'title' => trans('farmer.calendar_page.window_title', ['season' => $window['season']], $locale),
                'summary' => trans('farmer.calendar_page.window_summary', ['from' => $window['start']->format('M j'), 'until' => $window['end']->format('M j, Y')], $locale),
                'url' => null,
                'kind' => 'window',
                'active_now' => $window['start']->lte($today),
                'until' => $window['end'],
            ]);
        }

        return $events
            ->sortBy(fn (array $event): array => [$event['date']->toDateString(), $event['active_now'] ? 0 : 1, $event['title']])
            ->values()
            ->take(self::UPCOMING_LIMIT);
    }

    /** The Lian (Philippine) calendar day of a stored UTC time. */
    private function lianDay($time): Carbon
    {
        $c = $time instanceof Carbon ? $time : Carbon::parse($time);

        return Carbon::parse($c->copy()->timezone(BusinessClock::timezone())->toDateString())->startOfDay();
    }
}
