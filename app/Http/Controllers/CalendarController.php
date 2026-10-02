<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
            \App\Support\PageCache::queryVary($request, ['month', 'year']) + ['day' => now()->toDateString()],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function calendarData(Request $request): array
    {
        $month = max(1, min(12, (int) $request->integer('month', now()->month)));
        $year = (int) $request->integer('year', now()->year);

        $current = Carbon::create($year, $month, 1)->startOfMonth();
        $gridStart = $current->copy()->startOfWeek(Carbon::SUNDAY);
        $gridEnd = $current->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);
        $today = now()->startOfDay();
        $user = $request->user();

        $advisories = PlantingAdvisory::query()
            ->when(
                $user->role === User::ROLE_FARMER,
                fn ($query) => $query->published()->forBarangay($user->barangay),
                fn ($query) => $query->whereIn('status', [PlantingAdvisory::STATUS_PUBLISHED, PlantingAdvisory::STATUS_PENDING_REVIEW])
            )
            ->whereDate('valid_from', '<=', $gridEnd)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $gridStart))
            ->orderBy('valid_from')
            ->get();

        $feedEvents = FeedPost::query()
            ->whereNull('archived_at')
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$gridStart, $gridEnd])
            ->orderBy('event_date')
            ->get();

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

        $eventsByDay = [];
        foreach ($advisories as $advisory) {
            $start = ($advisory->valid_from?->copy() ?? $gridStart->copy())->startOfDay();
            $end = ($advisory->valid_until?->copy() ?? $start->copy())->startOfDay();

            if ($start->lt($gridStart)) {
                $start = $gridStart->copy();
            }
            if ($end->gt($gridEnd)) {
                $end = $gridEnd->copy();
            }

            for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
                $eventsByDay[$day->toDateString()][] = $this->advisoryCalendarEvent($advisory);
            }
        }

        foreach ($feedEvents as $post) {
            $eventsByDay[$post->event_date->toDateString()][] = $this->feedCalendarEvent($post);
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

        $upcomingAdvisories = $advisories
            ->filter(fn (PlantingAdvisory $advisory) => ($advisory->valid_until ?? $advisory->valid_from) && ($advisory->valid_until ?? $advisory->valid_from)->gte($today))
            ->map(fn (PlantingAdvisory $advisory) => $this->advisoryCalendarEvent($advisory));

        $upcomingFeedEvents = $feedEvents
            ->filter(fn (FeedPost $post) => $post->event_date && $post->event_date->copy()->endOfDay()->gte($today))
            ->map(fn (FeedPost $post) => $this->feedCalendarEvent($post));

        $upcoming = $upcomingAdvisories
            ->concat($upcomingFeedEvents)
            ->concat($schedules->flatMap(function (FarmerPlantingSchedule $schedule) use ($today) {
                $events = [];
                if ($schedule->planting_date->gte($today)) {
                    $events[] = $this->scheduleCalendarEvent($schedule, false);
                }
                $harvest = $schedule->scheduled_harvest_date ?? $schedule->harvest_month;
                if ($harvest?->gte($today)) {
                    $events[] = $this->scheduleCalendarEvent($schedule, true);
                }

                return $events;
            }))
            ->sortBy('starts_at')
            ->take(6);

        $previous = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();

        return [
            'current' => $current,
            'today' => $today,
            'weeks' => $weeks,
            'eventsByDay' => $eventsByDay,
            'upcoming' => $upcoming,
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

    private function advisoryCalendarEvent(PlantingAdvisory $advisory): array
    {
        return [
            'title' => $advisory->title,
            'summary' => $advisory->summary,
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
