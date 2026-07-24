<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClimateRecord extends Model
{
    use HasFactory;

    public const SEASON_WET = 'Wet';

    public const SEASON_DRY = 'Dry';

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
}
