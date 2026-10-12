<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Panel revision 4.2: one month's predicted and actual yield (t/ha) for Lian (barangay null) or one barangay. */
class MunicipalYieldPrediction extends Model
{
    protected $fillable = ['year', 'month', 'season', 'barangay', 'predicted_yield', 'actual_yield', 'split', 'model', 'source'];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'predicted_yield' => 'float',
        'actual_yield' => 'float',
    ];
}
