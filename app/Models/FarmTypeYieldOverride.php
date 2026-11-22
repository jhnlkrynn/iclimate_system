<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmTypeYieldOverride extends Model
{
    protected $fillable = [
        'rainfed_diff_percent',
        'note',
        'updated_by',
    ];

    protected $casts = [
        'rainfed_diff_percent' => 'decimal:2',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
