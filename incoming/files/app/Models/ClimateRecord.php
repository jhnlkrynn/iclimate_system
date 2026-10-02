<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property-read Carbon|null $record_date
 */
class ClimateRecord extends Model
{
    use HasFactory;

    public const SEASON_WET = 'Wet';

    public const SEASON_DRY = 'Dry';

    /**
     * Numeric guardrails added by the 2026-09 data-quality fix, after an
     * audit found a manually-entered ClimateRecord row (5000mm rainfall,
     * 100C temperature, 500km/h wind, future-dated) had no upper bound to
     * block it. These are PRAGMATIC DATA-ENTRY GUARDRAILS, not scientifically
     * derived Lian-specific thresholds. This is the single source of truth
     * for these bounds — both input validation (ClimateRecordController) and
     * analytics consumption (scopePlausible() below) reference these
     * constants rather than each keeping its own copy.
     */

    /**
     * Absolute zero (-273.15C) is the one physically defensible floor with no
     * domain-specific citation needed — temperature cannot go lower.
     */
    public const TEMPERATURE_MIN_C = -273.15;

    /**
     * The Ambulong historical dataset (database/data/Ambulong Monthly Data.csv,
     * 2015-2025, 131 usable months) observed a TMAX up to 35.9C. We allow
     * generous headroom above that observed extreme — roughly double it —
     * rather than asserting a "scientifically correct" Lian-specific ceiling
     * we cannot cite. This only needs to be wide enough to block obviously
     * impossible entries (e.g. the historical 100C bad row), not to encode a
     * precise climatological limit.
     */
    public const TEMPERATURE_MAX_C = 72.0;

    /**
     * Same CSV's WIND_SPEED column tops out at 3.0 in its own (unconfirmed,
     * possibly m/s or PAGASA-class) units, so it gives no directly usable
     * ceiling for this general-purpose field, which has historically stored
     * values up to ~80 in ad hoc units. We instead use a generously wide
     * safety margin: even the most extreme recorded tropical cyclone wind
     * speeds on Earth are well under 300 (in whatever comparable units are in
     * use), so a ceiling here is set high enough to admit any real extreme
     * weather reading while still rejecting an obviously impossible entry
     * like the historical 500 value. This is a guardrail, not a scientific
     * threshold.
     */
    public const WIND_SPEED_MAX = 250.0;

    /**
     * The Ambulong dataset's observed monthly rainfall max is 710.7mm. We
     * allow roughly double that as headroom for a real extreme-rainfall
     * month while still rejecting an obviously impossible entry like the
     * historical 5000mm bad row.
     */
    public const RAINFALL_MAX_MM = 1500.0;

    protected $fillable = [
        'record_date',
        'rainfall',
        'temperature',
        'humidity',
        'wind_speed',
        'season',
        'source',
    ];

    protected $casts = [
        'record_date' => 'date',
        'rainfall' => 'decimal:2',
        'temperature' => 'decimal:2',
        'humidity' => 'decimal:2',
        'wind_speed' => 'decimal:2',
    ];

    /**
     * Query-time filter for "physically plausible" rows, sharing the exact
     * same bounds as ClimateRecordController's input validation. Added by
     * the 2026-09 PredictionEngine data-quality fix: analytics consumers
     * (e.g. PredictionEngine::buildModelInput()) that read raw ClimateRecord
     * rows for derived features must exclude physically-impossible rows
     * (like the historical "sir mags" row: 5000mm rainfall, 100C, 500km/h
     * wind, future-dated) WITHOUT hardcoding any row ID — this scope
     * generalizes the same bounds check to any current or future bad row.
     *
     * This is a query-time filter only: it never deletes or modifies rows,
     * it only excludes them from THIS query's result set.
     */
    public function scopePlausible(Builder $query): Builder
    {
        return $query
            ->where('record_date', '<=', \App\Support\BusinessClock::today()->toDateString())
            ->where(function (Builder $q) {
                $q->whereNull('temperature')
                    ->orWhereBetween('temperature', [self::TEMPERATURE_MIN_C, self::TEMPERATURE_MAX_C]);
            })
            ->where(function (Builder $q) {
                $q->whereNull('wind_speed')
                    ->orWhereBetween('wind_speed', [0, self::WIND_SPEED_MAX]);
            })
            ->where(function (Builder $q) {
                $q->whereNull('rainfall')
                    ->orWhereBetween('rainfall', [0, self::RAINFALL_MAX_MM]);
            })
            ->where(function (Builder $q) {
                $q->whereNull('humidity')
                    ->orWhereBetween('humidity', [0, 100]);
            });
    }
}
