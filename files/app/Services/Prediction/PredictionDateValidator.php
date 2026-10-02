<?php

namespace App\Services\Prediction;

use Carbon\CarbonImmutable;

final class PredictionDateValidator
{
    public const ERROR_MESSAGE = 'Prediction cannot be generated because the selected date has already passed. Please select a valid future date.';

    public function validate(CarbonImmutable $targetDate): ?string
    {
        // Compare calendar dates: the target is a plain date (parsed as UTC midnight), "today" is the Manila date.
        return $targetDate->toDateString() > \App\Support\BusinessClock::today()->toDateString()
            ? null
            : self::ERROR_MESSAGE;
    }

    public static function defaultTargetDate(): CarbonImmutable
    {
        return \App\Support\BusinessClock::now()->toImmutable()->addMonthNoOverflow();
    }
}
