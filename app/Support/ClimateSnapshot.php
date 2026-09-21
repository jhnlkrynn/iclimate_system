<?php

namespace App\Support;

use App\Models\ClimateRecord;
use Illuminate\Support\Facades\Cache;
use Throwable;

class ClimateSnapshot
{
    public static function latest(): ?ClimateRecord
    {
        return Cache::remember('climate-snapshot:latest', now()->addMinute(), function (): ?ClimateRecord {
            try {
                // ->plausible() excludes physically-impossible rows (e.g. the
                // historical "sir mags" row: 5000mm rainfall, 100C, 500km/h
                // wind, future-dated) so this public-facing hero widget never
                // surfaces a data-entry error as if it were real weather.
                return ClimateRecord::query()->plausible()->latest('record_date')->first();
            } catch (Throwable) {
                return null;
            }
        });
    }
}
