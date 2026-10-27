<?php

namespace App\Support;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * The expected harvest date of a planting from its NSIC variety's maturity (days after sowing, DAS).
 *
 * Which trial figure is used: transplanted plantings read TPR, direct wet / dry seeded ones DWSR, and the season (Dry →
 * DS, Wet → WS) picks the column. An empty cell falls back, in order, to the same method in the other season, the
 * other method in the same season, the NSIC seed-production maturity, and finally the dashboard's typical season
 * length (config farmer_dashboard.crop.default_duration_days, 115 days). "basis" says which one was used.
 *
 * Maturity counts from sowing: a sowing / direct-seeding date gets date + DAS, a transplanting date
 * date + (DAS − seedling age). The window is the trials' min-max range when the NSIC list gives one, else ± 5 days.
 */
final class VarietyHarvestEstimator
{
    public const TRANSPLANTED = 'Transplanted';

    public const DIRECT_WET = 'Direct Wet Seeded';

    public const DIRECT_DRY = 'Direct Dry Seeded';

    public const ESTABLISHMENTS = [self::TRANSPLANTED, self::DIRECT_WET, self::DIRECT_DRY];

    public const DATE_BASES = ['sowing', 'transplanting'];

    /**
     * From a planting schedule (its variety, planting date, season, crop establishment, date basis and seedling age),
     * or from a variety (or none) and those values given separately.
     *
     * @return array{expected_harvest_date: Carbon, window_start: Carbon, window_end: Carbon, maturity_days_used: int,
     *     basis: string, basis_code: string, combo: string|null, season: string, method: string, establishment_known: bool}|null
     *     null only when there is no date to count from.
     */
    public function estimate(
        FarmerPlantingSchedule|RiceVariety|null $subject,
        CarbonInterface|string|null $date = null,
        ?string $season = null,
        ?string $establishment = null,
        ?string $dateBasis = null,
        ?int $seedlingAge = null,
    ): ?array {
        $variety = $subject;
        if ($subject instanceof FarmerPlantingSchedule) {
            $variety = $subject->rice_variety_id ? $subject->riceVariety : null;
            $date ??= $subject->planting_date;
            $season ??= $subject->season;
            $establishment ??= $subject->crop_establishment;
            $dateBasis ??= $subject->date_basis;
            $seedlingAge ??= $subject->seedling_age_days;
        }
        if ($date === null || $date === '') {
            return null;
        }

        $date = Carbon::parse($date instanceof CarbonInterface ? $date->toDateString() : $date)->startOfDay();
        $season = in_array($season, ['Wet', 'Dry'], true) ? $season : RiceSeason::forMonth($date->month);
        $establishmentKnown = in_array($establishment, self::ESTABLISHMENTS, true);
        $method = $establishmentKnown && $establishment !== self::TRANSPLANTED ? 'dwsr' : 'tpr';
        $fromTransplanting = $dateBasis === 'transplanting' && $method === 'tpr';
        $seedlingAge = $fromTransplanting ? max(0, $seedlingAge ?? (int) config('rice_varieties.default_seedling_age_days', 21)) : 0;

        [$range, $code, $combo] = $this->maturity($variety instanceof RiceVariety ? $variety : null, $method, $season === 'Dry' ? 'ds' : 'ws');

        $days = (int) round($range['avg']);
        $offset = max(1, $days - $seedlingAge);
        $expected = $date->copy()->addDays($offset);
        if ($range['min'] !== null && $range['max'] !== null && $range['min'] <= $days && $days <= $range['max']) {
            $start = $date->copy()->addDays(max(1, (int) round($range['min']) - $seedlingAge));
            $end = $date->copy()->addDays(max(1, (int) round($range['max']) - $seedlingAge));
        } else {
            $spread = (int) config('rice_varieties.window_days', 5);
            [$start, $end] = [$expected->copy()->subDays($spread), $expected->copy()->addDays($spread)];
        }

        return [
            'expected_harvest_date' => $expected,
            'window_start' => $start,
            'window_end' => $end,
            'maturity_days_used' => $days,
            'basis' => $this->basis($code, $variety instanceof RiceVariety, $season, $method, $establishmentKnown, $days),
            'basis_code' => $code,
            'combo' => $combo,
            'season' => $season,
            'method' => $method,
            'establishment_known' => $establishmentKnown,
        ];
    }

    /**
     * The maturity range to use, which fallback it came from and which column.
     *
     * @return array{0: array{avg: float, min: float|null, max: float|null}, 1: string, 2: string|null}
     */
    private function maturity(?RiceVariety $variety, string $method, string $season): array
    {
        if ($variety !== null) {
            $otherSeason = $season === 'ds' ? 'ws' : 'ds';
            $otherMethod = $method === 'tpr' ? 'dwsr' : 'tpr';
            foreach ([
                'trial' => $method.'_'.$season,
                'other_season' => $method.'_'.$otherSeason,
                'other_method' => $otherMethod.'_'.$season,
                'seed_production' => 'seed_production',
            ] as $code => $combo) {
                $range = $variety->range('maturity', $combo);
                if ($range['avg'] !== null && $range['avg'] > 0) {
                    return [$range, $code, $combo];
                }
            }
        }

        return [['avg' => (float) config('farmer_dashboard.crop.default_duration_days', 115), 'min' => null, 'max' => null], 'default', null];
    }

    private function basis(string $code, bool $hasVariety, string $season, string $method, bool $establishmentKnown, int $days): string
    {
        $seasonName = __('rice_varieties.season_name.'.$season);
        $otherSeason = __('rice_varieties.season_name.'.($season === 'Dry' ? 'Wet' : 'Dry'));
        $methodName = __('rice_varieties.method_name.'.$method);
        $otherMethod = __('rice_varieties.method_name.'.($method === 'tpr' ? 'dwsr' : 'tpr'));

        $sentence = match ($code) {
            'trial' => __('rice_varieties.basis.trial', ['season' => $seasonName, 'method' => $methodName]),
            'other_season' => __('rice_varieties.basis.other_season', ['season' => $seasonName, 'other' => $otherSeason, 'method' => $methodName]),
            'other_method' => __('rice_varieties.basis.other_method', ['season' => $seasonName, 'method' => $methodName, 'other' => $otherMethod]),
            'seed_production' => __('rice_varieties.basis.seed_production'),
            default => __($hasVariety ? 'rice_varieties.basis.default' : 'rice_varieties.basis.no_variety', ['days' => $days]),
        };

        return $code !== 'default' && ! $establishmentKnown ? $sentence.' '.__('rice_varieties.basis.assumed_transplanted') : $sentence;
    }
}
