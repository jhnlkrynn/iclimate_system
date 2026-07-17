<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalWeatherData extends Model
{
    protected $fillable = [
        'source',
        'location_name',
        'barangay_id',
        'latitude',
        'longitude',
        'forecast_date',
        'forecast_time',
        'weather_code',
        'temperature',
        'temperature_max',
        'temperature_min',
        'humidity',
        'rainfall_mm',
        'precipitation_probability',
        'wind_speed',
        'soil_temperature',
        'soil_moisture',
        'evapotranspiration_mm',
        'raw_response',
        'fetched_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'forecast_date' => 'date',
        'raw_response' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function advisories(): HasMany
    {
        return $this->hasMany(PlantingAdvisory::class, 'weather_data_id');
    }

    public function scopeLatestSuccessful(Builder $query): Builder
    {
        return $query->where('source', 'Open-Meteo')->latest('fetched_at');
    }

    public function freshnessLabel(): string
    {
        $hours = $this->fetched_at?->diffInHours(now()) ?? 999;

        return match (true) {
            $hours < 2 => 'fresh',
            $hours <= 6 => 'delayed',
            default => 'outdated',
        };
    }

    public function isOutdated(): bool
    {
        return $this->freshnessLabel() === 'outdated';
    }
}
