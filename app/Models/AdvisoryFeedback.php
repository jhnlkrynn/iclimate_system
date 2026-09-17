<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisoryFeedback extends Model
{
    protected $table = 'advisory_feedback';

    protected $fillable = [
        'planting_advisory_id',
        'user_id',
        'helpful',
    ];

    protected $casts = [
        'helpful' => 'boolean',
    ];

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(PlantingAdvisory::class, 'planting_advisory_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
