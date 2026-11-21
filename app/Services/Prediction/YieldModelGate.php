<?php

namespace App\Services\Prediction;

/**
 * Safety gate for rice-yield estimates (audit 2026-11-18, P1-3). ACCURACY NOT VERIFIED.
 *
 * The deployed model was trained on 48 municipal monthly rows; the application feeds it farm-level inputs with
 * different definitions (farm area instead of municipal area, a 6-month rainfall sum instead of a mean, max-min of
 * monthly means instead of TMAX-TMIN). Every estimate it gives today lies between 4.0 and 4.2 t/ha whatever the
 * input. Until the model is re-evaluated against independent observations, an estimate whose input falls outside
 * the range the model was trained on is not shown as an agricultural figure.
 *
 * Modes (config prediction.rice_yield.gate / ICLIMATE_YIELD_GATE):
 *   withhold  (default) such an estimate is unavailable: no number is shown, the reason is.
 *   label     the number is shown, marked "unvalidated estimate, not a recommendation".
 *   off       no gate (only for a model that has passed validation).
 *
 * Under "withhold" an in-range estimate is also withheld until prediction.rice_yield.accuracy_verified is true
 * (verification 2026-11-18: once the climate inputs follow the target month, in-range inputs occur, and an
 * in-range estimate is still not a verified one).
 *
 * Nothing is deleted or rewritten: stored predictions stay in yield_predictions as history.
 */
final class YieldModelGate
{
    public const WITHHOLD = 'withhold';

    public const LABEL = 'label';

    public const OFF = 'off';

    public const REASON = 'Harvest estimate not available: the yield model has not been validated for these farm conditions (accuracy not verified).';

    /** Stored rows whose basis comes from the yield model (directly or averaged). */
    public const MODEL_BASES = ['farm_model', 'barangay_model', 'farm_average'];

    public static function mode(): string
    {
        $mode = (string) config('prediction.rice_yield.gate', self::WITHHOLD);

        return in_array($mode, [self::WITHHOLD, self::LABEL, self::OFF], true) ? $mode : self::WITHHOLD;
    }

    /**
     * @param  array<string, mixed>|null  $modelInput  application input names (area, rainfall_6m, temp_range, ...)
     * @return array{compatible: bool, outside: list<string>, within_range?: bool}
     *                compatible = may be shown as a number; within_range = inside the training envelope
     */
    public static function assess(?array $modelInput): array
    {
        if (! is_array($modelInput) || $modelInput === []) {
            return ['compatible' => false, 'outside' => ['model_input'], 'within_range' => false];   // cannot be checked: not shown as validated
        }
        $outside = [];
        foreach ((array) config('prediction.rice_yield.training_envelope', []) as $feature => [$low, $high]) {
            $value = $modelInput[$feature] ?? null;
            if (! is_numeric($value) || (float) $value < (float) $low || (float) $value > (float) $high) {
                $outside[] = $feature;
            }
        }
        foreach ((array) config('prediction.rice_yield.extended_split_envelope', []) as $feature => [$low, $high]) {
            if (! array_key_exists($feature, $modelInput)) {
                continue;
            }
            $value = $modelInput[$feature];
            if (! is_numeric($value) || (float) $value < (float) $low || (float) $value > (float) $high) {
                $outside[] = $feature;
            }
        }

        return ['compatible' => $outside === [] && self::accuracyVerified(), 'outside' => $outside, 'within_range' => $outside === []];
    }

    /**
     * True when a STORED model estimate may be shown as a number: a planting schedule's estimated harvest, a finished
     * yield request (its total and outlook too) or the survey about it. Such rows were saved without a checkable model
     * input, many before this gate existed, so under "withhold" they stay hidden until accuracy is verified
     * (verification 2026-11-18: 6,606 schedules of 715 Farmers still showed a saved estimate on the calendar).
     */
    public static function showsStoredEstimate(): bool
    {
        return self::mode() !== self::WITHHOLD || self::accuracyVerified();
    }

    public static function accuracyVerified(): bool
    {
        return (bool) config('prediction.rice_yield.accuracy_verified', false);
    }

    /** True when an estimate with this input may be shown as a number. */
    public static function shows(?array $modelInput): bool
    {
        return self::mode() !== self::WITHHOLD || self::assess($modelInput)['compatible'];
    }

    /** True when a stored yield_predictions row may be shown as a number. */
    public static function showsStored(?string $basis, mixed $modelInput): bool
    {
        if (self::mode() !== self::WITHHOLD || ! in_array($basis, self::MODEL_BASES, true)) {
            return true;
        }
        // An average of farm-model estimates inherits their status; a row without input cannot be checked.
        return $basis !== 'farm_average' && self::assess(is_array($modelInput) ? $modelInput : null)['compatible'];
    }
}
