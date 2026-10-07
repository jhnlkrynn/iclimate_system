<?php

namespace App\Models;

use App\Support\RiceSeason;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manuscript – Naicel (2026-10-08), Phase 5: one requested rice-yield prediction and its result (see the migration). */
class YieldPredictionRequest extends Model
{
    public const SCOPE_FARM = 'farm';

    public const SCOPE_BARANGAY = 'barangay';

    public const SCOPE_MUNICIPAL = 'municipal';

    public const QUEUED = 'queued';

    public const DONE = 'done';

    public const FAILED = 'failed';

    /** The usual harvest month of each season: the month a season's prediction is made for. */
    public const HARVEST_MONTH = [RiceSeason::WET => 9, RiceSeason::DRY => 3];

    protected $fillable = [
        'user_id', 'scope', 'barangay', 'season', 'farm_type', 'area_hectares', 'target_month', 'status',
        'predicted_yield', 'total_tons', 'historical_average', 'impact_level', 'error', 'details', 'completed_at',
    ];

    protected $casts = [
        'area_hectares' => 'decimal:2',
        'predicted_yield' => 'decimal:2',
        'total_tons' => 'decimal:2',
        'historical_average' => 'decimal:2',
        'target_month' => 'date',
        'details' => 'array',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The next harvest month of the season from today (inside the 12 months the weather forecast covers). */
    public static function targetMonthFor(string $season, ?CarbonImmutable $today = null): CarbonImmutable
    {
        $today ??= CarbonImmutable::now(\App\Support\BusinessClock::timezone())->startOfMonth();
        $target = $today->setDate($today->year, self::HARVEST_MONTH[$season], 1);

        return $target->lt($today) ? $target->addYear() : $target;
    }
}
