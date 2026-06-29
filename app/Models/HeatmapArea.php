<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HeatmapArea extends Model
{
    use HasFactory;

    protected $fillable = [
        'barangay',
        'risk_level',
        'risk_type',
        'description',
    ];
}