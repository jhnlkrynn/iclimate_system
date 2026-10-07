<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\User;
use App\Services\Prediction\SeasonYield;
use App\Support\ActiveFarm;
use App\Support\FarmSize;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * Farmer Home's key figures and its yield comparison (2026-10-08, Farmer – Naicel), from the farmer's own saved records
 * and the parts the page already shows. No provider is called and no model is run.
 *
 * Up to four tiles, each shown only when its figure exists (a missing one is asked for in the notification bell):
 *   - farm:     the size of the farm being viewed (a known size in hectares, or the estimate the farmer picked);
 *   - crop:     days until planting, or days until harvest with how far the crop is, from the current planting plan;
 *   - expected: this season's expected harvest in tons = the expected yield (t/ha) × the farm's known size;
 *   - rain:     how many of the next days have rain, from the same 7-day forecast as the "Next 7 Days" card.
 *
 * The comparison is one small chart in t/ha: this season's estimate, the usual yield in Lian for this month, and the
 * farmer's last recorded harvest. It is shown with at least two of the three.
 */
class FarmerHomeKpis
{
    /** A plan whose harvest date passed this long ago without a recorded harvest is no longer "the current crop". */
    public const OVERDUE_DAYS = 60;

    public function __construct(private readonly SeasonYield $seasonYield) {}

    /**
     * @param  ?Farm  $farm  the farm being viewed (null: no farms, or before the farms table exists)
     * @param  ?Farm  $onlyFarm  with two or more farms: the farm whose plans count; otherwise null (all of the farmer's plans)
     * @param  array<string, mixed>  $harvest  the page's expected-yield card (FarmerDashboardService::harvest)
     * @param  array<string, mixed>  $week  the page's 7-day forecast (FarmerDashboardService::week)
     * @return array{tiles: array<int, array<string, mixed>>, compare: array<string, mixed>}
     */
    public function for(User $user, ?Farm $farm, ?Farm $onlyFarm, array $harvest, array $week, CarbonImmutable $now): array
    {
        $area = rescue(fn (): ?float => $this->knownArea($user, $farm), null, false);

        $tiles = array_values(array_filter([
            rescue(fn (): ?array => $this->farmTile($user, $farm), null, false),
            rescue(fn (): ?array => $this->cropTile($user, $onlyFarm, $now), null, false),
            rescue(fn (): ?array => $this->expectedTile($harvest, $area), null, false),
            rescue(fn (): ?array => $this->rainTile($week), null, false),
        ]));

        return [
            'tiles' => $tiles,
            'compare' => rescue(fn (): array => $this->compare($user, $onlyFarm, $harvest, $now), ['state' => 'nodata'], false),
        ];
    }

    /** The farm's size as a measured number of hectares; null for an estimate or no size (never made up). */
    private function knownArea(User $user, ?Farm $farm): ?float
    {
        if ($farm !== null) {
            return $farm->knownHectares();
        }
        if (ActiveFarm::available()) {
            return null;                                              // the farmer has no farm yet
        }
        $area = (float) ($user->farmerProfile?->farm_area ?? 0);

        return $area > 0 ? $area : null;
    }

    /** @return array<string, mixed>|null */
    private function farmTile(User $user, ?Farm $farm): ?array
    {
        $tile = ['key' => 'farm', 'icon' => 'map', 'label' => __('farmer.kpis.farm.label'), 'href' => route('farmer.my-farm')];

        if ($farm === null) {
            $area = $this->knownArea($user, null);

            return $area === null ? null : $tile + ['value' => $this->number($area), 'unit' => 'ha', 'note' => $this->farmNote($user->farmerProfile?->farm_type, 1)];
        }

        $note = $this->farmNote($farm->farm_type, ActiveFarm::farms($user)->count());
        if (($known = $farm->knownHectares()) !== null) {
            return $tile + ['value' => $this->number($known), 'unit' => 'ha', 'note' => $note];
        }
        if ($farm->estimateRange() !== null) {
            // An estimate is shown as the range the farmer picked, and says it is an estimate.
            return $tile + ['value' => FarmSize::rangeLabel((string) $farm->farm_size_estimate_range), 'unit' => null,
                'note' => trim(__('farms.size.estimated').($note !== null ? ' · '.$note : ''))];
        }

        return null;
    }

    /** "Irrigated" / "Rainfed", and "2 farms" when the farmer has more than one. */
    private function farmNote(?string $type, int $farms): ?string
    {
        $parts = [];
        if (in_array($type, FarmerPlantingSchedule::FARM_TYPES, true)) {
            $parts[] = __('farmer.kpis.farm.type.'.strtolower($type));
        }
        if ($farms > 1) {
            $parts[] = __('farmer.kpis.farm.farms', ['count' => $farms]);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** The farmer's planting plans (one farm's when given; plans older than farms count as the primary farm's). */
    private function plans(User $user, ?Farm $onlyFarm): Builder
    {
        return FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            ->when($onlyFarm !== null && SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), fn ($q) => $q->where(fn ($w) => $w->where('farm_id', $onlyFarm->getKey())
                ->when($onlyFarm->is_primary, fn ($p) => $p->orWhereNull('farm_id'))));
    }

    /**
     * From the newest planting plan that has no harvest recorded: days until planting, days until harvest (with how
     * much of the growing time has passed), or that the harvest date has passed.
     *
     * @return array<string, mixed>|null
     */
    private function cropTile(User $user, ?Farm $onlyFarm, CarbonImmutable $now): ?array
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules')) {
            return null;
        }
        $plan = $this->plans($user, $onlyFarm)
            ->whereNotNull('planting_date')
            ->when(SchemaCache::hasColumn('farmer_planting_schedules', 'harvested_on'), fn ($q) => $q->whereNull('harvested_on'))
            ->when(SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield'), fn ($q) => $q->where(fn ($w) => $w->whereNull('actual_yield')->orWhere('actual_yield', '<=', 0)))
            ->orderByDesc('planting_date')->orderByDesc('id')->first();
        if ($plan === null) {
            return null;
        }

        // Calendar dates in Manila time (the date columns hold a plain date).
        $today = CarbonImmutable::parse($now->toDateString());
        $planted = CarbonImmutable::parse($plan->planting_date->toDateString());
        $harvest = ($date = $plan->harvestDateForPlanning()) ? CarbonImmutable::parse($date->toDateString()) : null;
        $day = fn (CarbonImmutable $d): string => $d->locale(app()->getLocale())->translatedFormat($d->year === $today->year ? 'M j' : 'M j, Y');
        $tile = ['key' => 'crop', 'icon' => 'sprout', 'href' => route('farmer.my-farm')];

        if ($planted->gt($today)) {
            $days = (int) $today->diffInDays($planted);

            return $tile + ['label' => __('farmer.kpis.crop.to_planting'), 'value' => (string) $days, 'unit' => trans_choice('farmer.kpis.days', $days),
                'note' => __('farmer.kpis.crop.planting_on', ['date' => $day($planted)])];
        }
        if ($harvest === null) {
            return null;
        }
        if ($harvest->lt($today)) {
            $late = (int) $harvest->diffInDays($today);

            return $late > self::OVERDUE_DAYS ? null : $tile + ['label' => __('farmer.kpis.crop.passed'), 'value' => (string) $late, 'unit' => trans_choice('farmer.kpis.days_ago', $late),
                'note' => __('farmer.kpis.crop.record')];
        }

        $days = (int) $today->diffInDays($harvest);
        $total = (int) $planted->diffInDays($harvest);

        return $tile + ['label' => __('farmer.kpis.crop.to_harvest'), 'value' => (string) $days, 'unit' => trans_choice('farmer.kpis.days', $days),
            'note' => __('farmer.kpis.crop.harvest_on', ['date' => $day($harvest)]),
            'progress' => $total > 0 ? max(0, min(100, (int) round(($total - $days) / $total * 100))) : null];
    }

    /**
     * Expected harvest in tons: the expected yield (t/ha) times the farm's known size. Without a known size (an
     * estimate or none) no total is worked out.
     *
     * @param  array<string, mixed>  $harvest
     * @return array<string, mixed>|null
     */
    private function expectedTile(array $harvest, ?float $area): ?array
    {
        if (($harvest['state'] ?? null) !== 'ok' || $area === null || ! is_numeric($harvest['yield'] ?? null)) {
            return null;
        }
        $tons = (float) $harvest['yield'] * $area;

        return ['key' => 'expected', 'icon' => 'yield', 'href' => route('weather-predictions.index'), 'label' => __('farmer.kpis.expected.label'),
            'value' => number_format($tons, $tons < 10 ? 2 : 1), 'unit' => trans_choice('farmer.kpis.tons', $tons == 1.0 ? 1 : 2),
            'note' => __('farmer.kpis.expected.note', ['yield' => number_format((float) $harvest['yield'], 2), 'area' => $this->number($area)])];
    }

    /**
     * How many of the forecast days have rain (light, moderate or heavy), and whether any has heavy rain.
     *
     * @param  array<string, mixed>  $week
     * @return array<string, mixed>|null
     */
    private function rainTile(array $week): ?array
    {
        $days = collect($week['days'] ?? [])->filter(fn (array $d): bool => ($d['rain_key'] ?? null) !== null);
        if (($week['state'] ?? null) !== 'ok' || $days->isEmpty()) {
            return null;
        }
        $rainy = $days->filter(fn (array $d): bool => $d['rain_key'] !== 'none')->count();
        $heavy = $days->filter(fn (array $d): bool => $d['rain_key'] === 'heavy')->count();

        return ['key' => 'rain', 'icon' => 'rain', 'href' => route('weather-predictions.index'), 'label' => __('farmer.kpis.rain.label', ['days' => $days->count()]),
            'value' => (string) $rainy, 'unit' => __('farmer.kpis.rain.of', ['days' => $days->count()]),
            'note' => $heavy > 0 ? trans_choice('farmer.kpis.rain.heavy', $heavy, ['count' => $heavy]) : __('farmer.kpis.rain.no_heavy')];
    }

    /**
     * This season's estimate, the usual Lian yield for this month and the farmer's last recorded harvest, in t/ha.
     *
     * @param  array<string, mixed>  $harvest
     * @return array<string, mixed> state: ok | nodata
     */
    private function compare(User $user, ?Farm $onlyFarm, array $harvest, CarbonImmutable $now): array
    {
        $estimate = ($harvest['state'] ?? null) === 'ok' && is_numeric($harvest['yield'] ?? null) ? round((float) $harvest['yield'], 2) : null;
        $usual = is_numeric($harvest['usual'] ?? null) ? (float) $harvest['usual'] : $this->seasonYield->usual((int) $now->month);

        $last = null;
        if (SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            $record = $this->plans($user, $onlyFarm)->where('actual_yield', '>', 0)->orderByDesc('planting_date')->orderByDesc('id')->first();
            $last = $record ? round((float) $record->actual_yield, 2) : null;
        }

        $bars = array_filter([
            'estimate' => $estimate,
            'usual' => $usual !== null ? round($usual, 2) : null,
            'last' => $last,
        ], fn (?float $value): bool => $value !== null && $value > 0);
        if (count($bars) < 2) {
            return ['state' => 'nodata'];
        }

        return [
            'state' => 'ok',
            'keys' => array_keys($bars),
            'labels' => array_map(fn (string $key): string => __('farmer.kpis.compare.'.$key), array_keys($bars)),
            'values' => array_values($bars),
        ];
    }

    /** "1.5", "2", "0.25": hectares without trailing zeros. */
    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.') ?: '0';
    }
}
