<?php

namespace App\Services\Analytics;

use App\Models\FarmerPlantingSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The farmer's crop calendar and the weather advice that goes with it. No database access: it turns the farmer's planting
 * schedules (one per plot) and the forecast the dashboard already has into a timeline, reminders and advice.
 *
 * The stage windows are a TYPICAL rice calendar counted from the farmer's own planting and harvest dates. They are a guide,
 * not an agronomic prescription: the dashboard says so, and MAO advisories always come first.
 */
final class FarmerCropPlanService
{
    /** Days before planting that land preparation is expected to run. */
    public const LAND_PREP_DAYS = 14;

    /** Fertilizer windows, in days after planting: at planting, then two extra rounds. */
    public const FERTILIZER_WINDOWS = [
        'basal' => [0, 7, 'Fertilizer at planting'],
        'topdress1' => [14, 21, 'First extra fertilizer'],
        'topdress2' => [35, 42, 'Second extra fertilizer'],
    ];

    /** The harvest window either side of the expected harvest date. */
    public const HARVEST_DAYS_BEFORE = 7;

    public const HARVEST_DAYS_AFTER = 7;

    /** Reminders start this many days before an activity begins. */
    public const REMIND_DAYS = 3;

    /** A crop this short cannot have a second extra fertilizer. */
    private const MIN_SEASON_FOR_SECOND_TOPDRESS = 60;

    /** How many days of forecast the advice looks at. */
    private const ADVICE_LOOKAHEAD_DAYS = 2;

    /** At most this many plots are shown on the dashboard. */
    public const MAX_PLOTS = 4;

    /**
     * @param  Collection<int, FarmerPlantingSchedule>  $open  schedules that are not harvested yet
     * @param  array<int, array<string, mixed>>  $forecast  the dashboard's forecast days (date, rain, wind, high)
     * @return array{plots: array<int, array<string, mixed>>, more: int, reminders: array<int, array<string, string>>, advice: array<int, array<string, string>>}|null
     */
    public function build(Collection $open, array $forecast, CarbonImmutable $today): ?array
    {
        if ($open->isEmpty()) {
            return null;
        }

        $plots = $open->sortBy(fn (FarmerPlantingSchedule $s) => $s->planting_date?->timestamp ?? PHP_INT_MAX)->values()
            ->map(fn (FarmerPlantingSchedule $s, int $i) => $this->plot($s, $i + 1, $forecast, $today))
            ->filter()->values();

        if ($plots->isEmpty()) {
            return null;
        }

        return [
            'plots' => $plots->take(self::MAX_PLOTS)->all(),
            'more' => max(0, $plots->count() - self::MAX_PLOTS),
            'reminders' => $plots->flatMap(fn (array $p) => array_map(fn ($r) => $r + ['plot' => $p['label']], $p['reminders']))->all(),
            'advice' => $plots->flatMap(fn (array $p) => array_map(fn ($a) => $a + ['plot' => $p['label']], $p['advice']))->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    private function plot(FarmerPlantingSchedule $s, int $number, array $forecast, CarbonImmutable $today): ?array
    {
        if (! $s->planting_date) {
            return null;
        }
        $planting = CarbonImmutable::instance($s->planting_date)->startOfDay();
        $harvestDate = $s->harvestDateForPlanning();
        $harvest = $harvestDate ? CarbonImmutable::instance($harvestDate)->startOfDay() : null;

        $milestones = $this->milestones($planting, $harvest, $today);
        $current = collect($milestones)->firstWhere('state', 'current');
        $next = collect($milestones)->firstWhere('state', 'upcoming');

        $stage = match (true) {
            $current !== null => $current['label'],
            $today->lt($planting->subDays(self::LAND_PREP_DAYS)) => 'Waiting for planting',
            $harvest && $today->startOfDay()->gt($harvest->addDays(self::HARVEST_DAYS_AFTER)) => 'Past the expected harvest',
            default => 'Growing',
        };

        $reminders = $this->reminders($milestones, $today);
        $active = $current ?? ($next && $today->diffInDays($next['start']->startOfDay(), false) <= self::REMIND_DAYS ? $next : null);

        return [
            'label' => $this->label($s, $number),
            'stage' => $stage,
            'milestones' => $milestones,
            'next' => $next,
            'reminders' => $reminders,
            'advice' => $active ? $this->advice($active, $forecast, $today) : [],
            'edit_id' => $s->id,
        ];
    }

    private function label(FarmerPlantingSchedule $s, int $number): string
    {
        $parts = ['Plot '.$number];
        if ($s->season) {
            $parts[] = $s->season.' season';
        }
        if ($s->farm_area_hectares !== null && (float) $s->farm_area_hectares > 0) {
            $parts[] = number_format((float) $s->farm_area_hectares, 2).' ha';
        }
        $parts[] = 'planted '.$s->planting_date->format('M j');

        return implode(' · ', $parts);
    }

    /**
     * The crop calendar for one planting. `state` is done, current or upcoming, measured at the start of `$today`.
     *
     * @return array<int, array{key: string, kind: string, label: string, start: CarbonImmutable, end: CarbonImmutable, state: string}>
     */
    private function milestones(CarbonImmutable $planting, ?CarbonImmutable $harvest, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $items = [['land_prep', 'land_prep', 'Land preparation', $planting->subDays(self::LAND_PREP_DAYS), $planting->subDay()]];
        $items[] = ['planting', 'planting', 'Planting', $planting, $planting->addDays(2)];

        $season = $harvest ? (int) $planting->diffInDays($harvest, false) : null;
        foreach (self::FERTILIZER_WINDOWS as $key => [$from, $to, $label]) {
            if ($key === 'topdress2' && $season !== null && $season < self::MIN_SEASON_FOR_SECOND_TOPDRESS) {
                continue;
            }
            $items[] = [$key, 'fertilizer', $label, $planting->addDays($from), $planting->addDays($to)];
        }
        if ($harvest) {
            $items[] = ['harvest', 'harvest', 'Harvest', $harvest->subDays(self::HARVEST_DAYS_BEFORE), $harvest->addDays(self::HARVEST_DAYS_AFTER)];
        }

        usort($items, fn ($a, $b) => $a[3]->timestamp <=> $b[3]->timestamp);

        return array_map(fn (array $i) => [
            'key' => $i[0], 'kind' => $i[1], 'label' => $i[2], 'start' => $i[3], 'end' => $i[4],
            'state' => match (true) {
                $i[4]->lt($today) => 'done',
                $i[3]->lte($today) => 'current',
                default => 'upcoming',
            },
        ], $items);
    }

    /**
     * Something to do now, or starting in the next few days.
     *
     * @return array<int, array{title: string, when: string}>
     */
    private function reminders(array $milestones, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $out = [];
        foreach ($milestones as $m) {
            if ($m['state'] === 'current') {
                $out[] = ['title' => $m['label'].' is due now', 'when' => 'until '.$m['end']->format('M j')];
            } elseif ($m['state'] === 'upcoming') {
                $days = (int) $today->diffInDays($m['start']->startOfDay(), false);
                if ($days <= self::REMIND_DAYS) {
                    $out[] = ['title' => $m['label'].' starts '.($days === 1 ? 'tomorrow' : 'in '.$days.' days'), 'when' => $m['start']->format('l, M j')];
                }
            }
        }

        return $out;
    }

    /**
     * Rules that tie the activity to the forecast for today and the next two days. Nothing is generated: each line
     * comes from a rule and the forecast value that triggered it.
     *
     * @return array<int, array{severity: string, text: string}>
     */
    private function advice(array $activity, array $forecast, CarbonImmutable $today): array
    {
        $today = $today->startOfDay();
        $rain = $wind = $heat = null;
        foreach ($forecast as $day) {
            $ahead = (int) $today->diffInDays($day['date']->startOfDay(), false);
            if ($ahead < 0 || $ahead > self::ADVICE_LOOKAHEAD_DAYS) {
                continue;
            }
            $when = $ahead === 0 ? 'today' : ($ahead === 1 ? 'tomorrow' : 'in '.$ahead.' days');
            if ($rain === null && ($day['rain'] ?? null) !== null && $day['rain'] >= FarmerDashboardService::HEAVY_RAIN_MM) {
                $rain = ['mm' => $day['rain'], 'when' => $when];
            }
            if ($wind === null && ($day['wind'] ?? null) !== null && $day['wind'] >= FarmerDashboardService::STRONG_WIND_KMH) {
                $wind = ['kmh' => $day['wind'], 'when' => $when];
            }
            if ($heat === null && ($day['high'] ?? null) !== null && $day['high'] >= FarmerDashboardService::VERY_HOT_C) {
                $heat = ['c' => $day['high'], 'when' => $when];
            }
        }

        $out = [];
        $name = mb_strtolower($activity['label']);
        switch ($activity['kind']) {
            case 'fertilizer':
                if ($rain) {
                    $out[] = ['severity' => 'warning', 'text' => 'Hold off on the '.$name.': about '.number_format($rain['mm'], 0).' mm of rain is forecast '.$rain['when'].' and can wash fertilizer away. Apply it after the rain, when the field is not flooded.'];
                }
                if ($heat && ! $rain) {
                    $out[] = ['severity' => 'watch', 'text' => 'It will be very hot '.$heat['when'].' (up to '.number_format($heat['c'], 0).'°C). Apply the '.$name.' in the early morning or late afternoon.'];
                }
                break;
            case 'planting':
            case 'land_prep':
                if ($rain) {
                    $out[] = ['severity' => 'warning', 'text' => 'Heavy rain ('.number_format($rain['mm'], 0).' mm) is forecast '.$rain['when'].'. '.($activity['kind'] === 'planting' ? 'Consider planting after the rain, once the field drains, so seedlings are not washed out.' : 'Field work will be hard while the field is flooded; plan it for after the rain.')];
                }
                break;
            case 'harvest':
                if ($rain) {
                    $out[] = ['severity' => 'warning', 'text' => 'Rain ('.number_format($rain['mm'], 0).' mm) is forecast '.$rain['when'].'. If your grain is ready, harvest before it, and keep harvested palay covered and dry.'];
                }
                if ($wind) {
                    $out[] = ['severity' => 'watch', 'text' => 'Strong wind (up to '.number_format($wind['kmh'], 0).' km/h) is forecast '.$wind['when'].' and can lay down ripe rice. Harvest ripe fields first.'];
                }
                break;
        }

        return $out;
    }
}
