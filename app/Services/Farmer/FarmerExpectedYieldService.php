<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\PlantingAdvisory;
use App\Models\User;
use App\Services\Advisories\AdvisoryActionRules;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;

class FarmerExpectedYieldService
{
    /**
     * Build expected-vs-estimated yield data, comparison insights, and last harvest summary
     * strictly from stored farm/schedule/variety/forecast/advisory records.
     *
     * @param  array<string, mixed>  $harvest
     * @param  array<string, mixed>  $week
     * @return array{
     *     season_key: string,
     *     expected: ?float,
     *     source: string,
     *     source_label: string,
     *     estimate: ?float,
     *     diff_t_ha: ?float,
     *     diff_pct: ?int,
     *     insights: list<string>,
     *     last_harvest: ?array{tons: float, yield: ?float, area: ?float, has_area: bool, harvested_on: ?string}
     * }
     */
    public function resolve(
        User $user,
        ?Farm $farm,
        array $harvest,
        array $week,
        CarbonImmutable $now,
        string $locale = 'en',
    ): array {
        $fil = $locale === 'fil';
        $seasonInfo = RiceSeason::window($now);
        $seasonKey = $seasonInfo['season'].' '.$now->year;

        $lastHarvestPlan = $this->lastHarvestPlan($user, $farm);
        $activePlan = $this->activePlan($user, $farm);
        $lastHarvest = $this->formatLastHarvest($lastHarvestPlan, $farm);

        [$expected, $source, $sourceLabel] = $this->resolveExpected($farm, $seasonKey, $lastHarvestPlan, $activePlan, $fil);

        $estimate = ($harvest['state'] ?? null) === 'ok' && is_numeric($harvest['yield'] ?? null)
            ? round((float) $harvest['yield'], 2)
            : null;

        $diffTHa = ($estimate !== null && $expected !== null) ? round($estimate - $expected, 2) : null;
        $diffPct = ($diffTHa !== null && $expected > 0) ? (int) round(($diffTHa / $expected) * 100) : null;

        $insights = $this->buildInsights($harvest, $week, $expected, $estimate, $diffTHa, $activePlan, $farm?->barangay ?? $user->registeredBarangay(), $fil);

        return [
            'season_key' => $seasonKey,
            'expected' => $expected,
            'source' => $source,
            'source_label' => $sourceLabel,
            'estimate' => $estimate,
            'diff_t_ha' => $diffTHa,
            'diff_pct' => $diffPct,
            'insights' => $insights,
            'last_harvest' => $lastHarvest,
        ];
    }

    /**
     * @return array{0: ?float, 1: string, 2: string}
     */
    private function resolveExpected(?Farm $farm, string $seasonKey, ?FarmerPlantingSchedule $lastHarvestPlan, ?FarmerPlantingSchedule $activePlan, bool $fil): array
    {
        if ($farm !== null) {
            $dbExpected = (SchemaCache::hasColumn('farms', 'expected_yield_t_ha')
                && is_numeric($farm->expected_yield_t_ha)
                && (float) $farm->expected_yield_t_ha > 0
                && ($farm->expected_yield_season === null || $farm->expected_yield_season === $seasonKey))
                ? round((float) $farm->expected_yield_t_ha, 2)
                : null;
            $cachedExpected = \Illuminate\Support\Facades\Cache::get("farmer:expected-yield:{$farm->getKey()}:{$seasonKey}");
            $savedExpected = $dbExpected ?? (is_numeric($cachedExpected) && (float) $cachedExpected > 0 ? round((float) $cachedExpected, 2) : null);

            if ($savedExpected !== null) {
                return [
                    $savedExpected,
                    'saved',
                    $fil ? 'Inilagay mo para sa season na ito' : 'Saved by you for this season',
                ];
            }
        }

        if ($lastHarvestPlan !== null && is_numeric($lastHarvestPlan->actual_yield) && (float) $lastHarvestPlan->actual_yield > 0) {
            return [
                round((float) $lastHarvestPlan->actual_yield, 2),
                'last_harvest',
                $fil ? 'Mula sa huli mong ani' : 'Prefilled from your last harvest',
            ];
        }

        $variety = $activePlan?->riceVariety;
        if ($variety !== null && is_numeric($variety->yield_typical) && (float) $variety->yield_typical > 0) {
            $raw = (float) $variety->yield_typical;
            $tHa = round($raw > 20 ? $raw / 1000 : $raw, 2);

            return [
                $tHa,
                'variety',
                $fil
                    ? 'Mula sa karaniwang ani ng '.$variety->displayName().' (Rice Varieties)'
                    : 'Prefilled from '.$variety->displayName().' typical yield (Rice Varieties)',
            ];
        }

        return [null, 'none', $fil ? 'Wala pang nailagay' : 'Not set yet'];
    }

    private function lastHarvestPlan(User $user, ?Farm $farm): ?FarmerPlantingSchedule
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules') || ! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return null;
        }

        return FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            ->when($farm !== null && SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), function ($q) use ($farm): void {
                $q->where(fn ($w) => $w->where('farm_id', $farm->getKey())->when($farm->is_primary, fn ($p) => $p->orWhereNull('farm_id')));
            })
            ->where('actual_yield', '>', 0)
            ->orderByDesc('harvested_on')
            ->orderByDesc('planting_date')
            ->orderByDesc('id')
            ->first();
    }

    private function activePlan(User $user, ?Farm $farm): ?FarmerPlantingSchedule
    {
        if (! SchemaCache::hasTable('farmer_planting_schedules')) {
            return null;
        }

        return FarmerPlantingSchedule::query()
            ->with('riceVariety')
            ->where('user_id', $user->id)
            ->when($farm !== null && SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), function ($q) use ($farm): void {
                $q->where(fn ($w) => $w->where('farm_id', $farm->getKey())->when($farm->is_primary, fn ($p) => $p->orWhereNull('farm_id')));
            })
            ->whereNull('harvested_on')
            ->orderByDesc('planting_date')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return ?array{tons: float, yield: ?float, area: ?float, has_area: bool, harvested_on: ?string}
     */
    private function formatLastHarvest(?FarmerPlantingSchedule $plan, ?Farm $farm): ?array
    {
        if ($plan === null || ! is_numeric($plan->actual_yield) || (float) $plan->actual_yield <= 0) {
            return null;
        }

        $yield = round((float) $plan->actual_yield, 2);
        $area = (is_numeric($plan->farm_area_hectares) && (float) $plan->farm_area_hectares > 0)
            ? (float) $plan->farm_area_hectares
            : $farm?->knownHectares();

        $hasArea = $area !== null && $area > 0;
        $tons = $hasArea ? round($yield * $area, 2) : $yield;

        return [
            'tons' => $tons,
            'yield' => $hasArea ? $yield : null,
            'area' => $hasArea ? round($area, 2) : null,
            'has_area' => $hasArea,
            'harvested_on' => $plan->harvested_on?->format('M j, Y'),
        ];
    }

    /**
     * @param  array<string, mixed>  $harvest
     * @param  array<string, mixed>  $week
     * @return list<string>
     */
    private function buildInsights(
        array $harvest,
        array $week,
        ?float $expected,
        ?float $estimate,
        ?float $diffTHa,
        ?FarmerPlantingSchedule $activePlan,
        ?string $barangay,
        bool $fil,
    ): array {
        if ($estimate === null) {
            $reason = trim((string) ($harvest['reason'] ?? ''));
            $updateLine = $fil
                ? 'Awtomatikong nag-a-update ang tantiya tuwing 3 oras kapag kumpleto ang sukat ng bukid at datos ng panahon.'
                : 'Updates automatically every 3 hours once your farm size and weather data are ready.';

            return array_values(array_filter([$reason !== '' ? $reason : null, $updateLine]));
        }

        $insights = [];

        // Add stored 7-day forecast / stage insight without inventing numbers.
        $days = collect($week['days'] ?? []);
        $hasHeavyRain = $days->contains(fn (array $d): bool => ($d['rain_key'] ?? null) === 'heavy');
        $stage = null;
        if ($activePlan !== null && $activePlan->planting_date !== null) {
            $days = max(0, (int) $activePlan->planting_date->diffInDays(CarbonImmutable::now(\App\Support\BusinessClock::timezone()), false));
            $stage = match (true) {
                $days < 25 => $fil ? 'punla' : 'seedling',
                $days < 55 => $fil ? 'pagsuwi' : 'vegetative growth',
                $days < 85 => $fil ? 'pamumulaklak' : 'flowering',
                default => $fil ? 'paghinog' : 'ripening',
            };
        }
        if ($hasHeavyRain) {
            if ($stage) {
                $insights[] = $fil
                    ? "May inaasahang malakas na ulan sa yugto ng {$stage}. Panatilihing malinis ang mga kanal."
                    : "Heavy rain is forecast during {$stage}. Keep the canals clear.";
            } else {
                $insights[] = $fil
                    ? 'May inaasahang malakas na ulan ngayong linggo. Panatilihing malinis ang mga kanal.'
                    : 'Heavy rain is forecast this week. Keep the canals clear.';
            }
        }

        // Add latest active advisory action if space remains.
        if (count($insights) < 3 && SchemaCache::hasTable('planting_advisories')) {
            $advisory = PlantingAdvisory::query()->active()->forBarangay($barangay)->latest('published_at')->first();
            if ($advisory !== null) {
                $actions = app(AdvisoryActionRules::class)->forAdvisory($advisory, $fil ? 'fil' : 'en', $stage);
                if (! empty($actions[0]) && ! in_array($actions[0], $insights, true)) {
                    $insights[] = $actions[0];
                }
            }
        }

        return array_slice($insights, 0, 3);
    }
}
