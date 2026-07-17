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
                return ClimateRecord::query()->latest('record_date')->first();
            } catch (Throwable) {
                return null;
            }
        });
    }
}
