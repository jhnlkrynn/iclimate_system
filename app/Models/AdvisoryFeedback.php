<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdvisoryFeedback extends Model
{
    use HasFactory;

    protected $table = 'advisory_feedback';

    protected $fillable = [
        'planting_advisory_id',
        'user_id',
        'is_helpful',
        'comment',
        'mao_response',
        'mao_responded_at',
        'mao_responded_by',
    ];

    protected $casts = [
        'is_helpful' => 'boolean',
        'mao_responded_at' => 'datetime',
    ];

    public function advisory(): BelongsTo
    {
        return $this->belongsTo(PlantingAdvisory::class, 'planting_advisory_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mao_responded_by');
    }
}
