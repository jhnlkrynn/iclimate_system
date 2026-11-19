<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Barangay/season benchmark imported from an official seasonal production workbook. */
class HistoricalRiceProduction extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay', 'season', 'season_year', 'season_label', 'area_planted_hectares',
        'average_yield_t_per_hectare', 'total_production_tons', 'source_filename',
        'source_sheet', 'source_row', 'source_units',
    ];

    protected $casts = [
        'season_year' => 'integer',
        'area_planted_hectares' => 'decimal:8',
        'average_yield_t_per_hectare' => 'decimal:8',
        'total_production_tons' => 'decimal:8',
        'source_row' => 'integer',
    ];

    public function scopeForSeason($query, string $barangay, string $season, int $year)
    {
        return $query->where('barangay', $barangay)->where('season', $season)->where('season_year', $year);
    }
}
