<?php

namespace App\Support;

use App\Models\Farm;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * The transparency record behind one saved rice-yield estimate (owner request 2026-11-20): which model made it, when,
 * from which farm details, and every one of the model's twelve inputs with its value and where that value came from.
 * Built only from what was saved with the estimate; nothing here is recomputed or filled in.
 */
final class YieldEstimateRecord
{
    /** The deployed model's inputs, in its training order (rice_yield_service.py rice_features()), with units. */
    private const INPUTS = [
        'rainfall' => ['mm', 'target'],
        'temp_avg' => ['°C', 'target'],
        'temp_range' => ['°C', 'target'],
        'area' => ['ha', 'farm'],
        'previous_rainfall' => ['mm', 1],
        'previous_temp' => ['°C', 1],
        'rainfall_6m' => ['mm', 6],
        'temp_3m' => ['°C', 3],
        'temp_6m' => ['°C', 6],
        'seasonal_rainfall' => ['mm', 'season'],
        'seasonal_temp' => ['°C', 'season'],
        'season' => [null, 'calendar'],
    ];

    /** @param  array<string, mixed>  $estimate  a saved estimate (FarmerDashboardService::storedEstimate) */
    public static function from(array $estimate): ?array
    {
        $input = is_array($estimate['inputs_used'] ?? null) ? $estimate['inputs_used'] : ($estimate['model_input'] ?? null);
        $yield = $estimate['yield_tons_per_hectare'] ?? $estimate['predicted_yield'] ?? null;
        if (! is_array($input) || ! is_numeric($yield) || empty($estimate['target_month'])) {
            return null;
        }

        try {
            $target = CarbonImmutable::parse($estimate['target_month'].'-01');
        } catch (Throwable) {
            return null;
        }
        $sources = is_array($estimate['climate_sources'] ?? null) ? $estimate['climate_sources'] : [];
        $archive = (array) ($sources['reanalysis_months'] ?? []);
        $forecast = (array) ($sources['forecast_months'] ?? []);
        $weather = $estimate['engine_result']['weather'] ?? [];
        $locale = app()->getLocale();
        $t = fn (string $key, array $r = []): string => __('farmer.predictions.record.'.$key, $r);
        $month = fn (CarbonImmutable $m): string => $m->locale($locale)->translatedFormat('M Y');

        $inputs = [];
        foreach (self::INPUTS as $key => [$unit, $kind]) {
            $value = $input[$key] ?? null;
            $inputs[] = [
                'label' => $t('inputs.'.$key),
                'value' => $value === null ? '—' : (is_numeric($value) ? number_format((float) $value, 2).($unit ? ' '.$unit : '') : (string) $value),
                'source' => match (true) {
                    $kind === 'target' => $t('source_forecast_month', ['month' => $month($target)]),
                    $kind === 'farm' => self::farmSource($estimate, $t),
                    $kind === 'calendar' => $t('source_calendar', ['month' => $month($target)]),
                    $kind === 'season' => self::describe(self::seasonMonths($target), $target, $archive, $forecast, $t, $month),
                    default => self::describe(array_map(fn (int $i): CarbonImmutable => $target->subMonthsNoOverflow($i), range($kind, 1)), $target, $archive, $forecast, $t, $month),
                },
            ];
        }

        $spread = is_numeric($estimate['uncertainty'] ?? null) ? (float) $estimate['uncertainty'] : null;
        $ranAt = rescue(fn () => CarbonImmutable::parse((string) ($estimate['ran_at'] ?? ''))->timezone('Asia/Manila'), null, false);
        $forecastAt = rescue(fn () => CarbonImmutable::parse((string) ($weather['generated_at'] ?? ''))->timezone('Asia/Manila'), null, false);

        return [
            'yield' => round((float) $yield, 2),
            'spread' => $spread,
            'range' => $spread !== null ? [round(max(0, (float) $yield - $spread), 2), round((float) $yield + $spread, 2)] : null,
            'model' => (string) ($estimate['model'] ?? 'Random Forest'),
            'version' => (string) ($estimate['model_version'] ?? ''),
            'ran_at' => $ranAt,
            'target_month' => $month($target),
            'farm_type' => (string) ($input['farm_type'] ?? $estimate['farm_type'] ?? '—'),
            'inputs' => $inputs,
            'weather_model' => (string) ($weather['weather_model'] ?? ''),
            'weather_trained' => isset($weather['training_period']['from'], $weather['training_period']['to'])
                ? $weather['training_period']['from'].' – '.$weather['training_period']['to'] : null,
            'forecast_at' => $forecastAt,
            'within_range' => ($estimate['validation_status'] ?? null) === 'within_training_range',
            'outside_features' => \App\Services\Prediction\YieldModelGate::assess($input)['outside'],
            'checked' => (bool) ($estimate['accuracy_verified'] ?? false),
            'fallback' => (bool) ($estimate['fallback'] ?? false) || (bool) ($estimate['api_fallback_used'] ?? false),
        ];
    }

    /** @return list<CarbonImmutable> the whole model season around the target month (as YieldModelClimateWindow) */
    private static function seasonMonths(CarbonImmutable $target): array
    {
        $season = RiceSeason::forTrainedYieldModel((int) $target->format('n'));
        $months = [$target];
        for ($m = $target->subMonthNoOverflow(); RiceSeason::forTrainedYieldModel((int) $m->format('n')) === $season; $m = $m->subMonthNoOverflow()) {
            array_unshift($months, $m);
        }
        for ($m = $target->addMonthNoOverflow(); RiceSeason::forTrainedYieldModel((int) $m->format('n')) === $season; $m = $m->addMonthNoOverflow()) {
            $months[] = $m;
        }

        return $months;
    }

    /**
     * "May–Oct 2026: past weather from Open-Meteo, adjusted to Ambulong station" — consecutive months with the same
     * source are joined.
     *
     * @param  list<CarbonImmutable>  $months
     */
    private static function describe(array $months, CarbonImmutable $target, array $archive, array $forecast, callable $t, callable $label): string
    {
        $groups = [];
        foreach ($months as $m) {
            $key = $m->format('Y-m');
            $kind = match (true) {
                $key === $target->format('Y-m') || in_array($key, $forecast, true) => 'forecast',
                in_array($key, $archive, true) => 'archive',
                default => 'station',
            };
            $last = count($groups) - 1;
            if ($last >= 0 && $groups[$last]['kind'] === $kind) {
                $groups[$last]['to'] = $m;
            } else {
                $groups[] = ['kind' => $kind, 'from' => $m, 'to' => $m];
            }
        }

        return implode('; ', array_map(function (array $g) use ($t, $label): string {
            $span = $g['from']->eq($g['to']) ? $label($g['from']) : $label($g['from']).' – '.$label($g['to']);

            return $span.': '.$t('source_'.$g['kind']);
        }, $groups));
    }

    private static function farmSource(array $estimate, callable $t): string
    {
        return match ($estimate['farm_size_basis'] ?? null) {
            Farm::SIZE_MAPPED => $t('farm_mapped'),
            Farm::SIZE_ESTIMATED => $t('farm_estimated'),
            Farm::SIZE_EXACT => $t('farm_exact'),
            default => $t('farm_profile'),
        };
    }
}
