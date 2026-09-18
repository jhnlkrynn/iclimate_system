<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class RiceProduction extends Model
{
    use HasFactory;

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
