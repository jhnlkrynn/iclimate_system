<?php

namespace App\Services\Prediction;

use Carbon\CarbonImmutable;

final class PredictionDateValidator
{
    public const ERROR_MESSAGE = 'Prediction cannot be generated because the selected date has already passed. Please select a valid future date.';

    public function validate(CarbonImmutable $targetDate): ?string
    {
        return $targetDate->startOfDay()->greaterThan(CarbonImmutable::today())
            ? null
            : self::ERROR_MESSAGE;
    }

    public static function defaultTargetDate(): CarbonImmutable
    {
        return CarbonImmutable::now()->addMonthNoOverflow();
    }
}
