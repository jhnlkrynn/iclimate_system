<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RiceProduction extends Model
{
    use HasFactory;

    /**
     * yield_per_hectare unit: tons/hectare (t/ha). This is the unit assumed
     * everywhere this value flows — BarangayRiskMapService (as
     * 'predicted_yield' fed to DecisionSupportService/AgriculturalRiskScorer,
     * whose yield thresholds e.g. <2, <3, <4 are tons/hectare), and the
     * farmer/MAO dashboards. Do not feed a different unit (e.g. total
     * kilograms, or total production) into 'predicted_yield' downstream.
     */
    protected $fillable = [
        'barangay',
        'season',
        'irrigation_type',
        'yield_per_hectare',
        'area_hectares',
        'total_production',
        'year',
        'production_month',
        'remarks',
    ];

    protected $casts = [
        'yield_per_hectare' => 'decimal:2',
        'area_hectares' => 'decimal:2',
        'total_production' => 'decimal:2',
        'production_month' => 'date',
        'year' => 'integer',
    ];

    public function setProductionMonthAttribute(mixed $value): void
    {
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d');
        }

        $this->attributes['production_month'] = $value
            ? Carbon::createFromFormat(strlen((string) $value) === 7 ? 'Y-m' : 'Y-m-d', (string) $value)->startOfMonth()->toDateString()
            : null;
    }
}
