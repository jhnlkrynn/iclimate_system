<?php

namespace App\Http\Controllers;

use App\Models\FeedPost;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
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
                fn ($query) => $query->active()->forBarangay($user->barangay),
                fn ($query) => $query->whereIn('status', [PlantingAdvisory::STATUS_PUBLISHED, PlantingAdvisory::STATUS_PENDING_REVIEW])
            )
            ->whereDate('valid_from', '<=', $gridEnd)
            ->where(fn ($query) => $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', $gridStart))
            ->orderBy('valid_from')
            ->get();

        $feedEvents = FeedPost::query()
            ->where('show_on_calendar', true)
            ->whereNull('archived_at')
            ->whereNotNull('event_date')
            ->whereBetween('event_date', [$gridStart, $gridEnd])
            ->orderBy('event_date')
            ->get();

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
            ->sortBy('starts_at')
            ->take(6);

        $previous = $current->copy()->subMonth();
        $next = $current->copy()->addMonth();

        return view('calendar.index', [
            'current' => $current,
            'today' => $today,
            'weeks' => $weeks,
            'eventsByDay' => $eventsByDay,
            'upcoming' => $upcoming,
            'previousMonth' => $previous->month,
            'previousYear' => $previous->year,
            'nextMonth' => $next->month,
            'nextYear' => $next->year,
        ]);
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
}
