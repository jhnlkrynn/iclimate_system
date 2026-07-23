<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TyphoonSafetyResponse extends Model
{
    use HasFactory;

    public const STATUS_SAFE = 'safe';

    public const STATUS_NEEDS_HELP = 'needs_help';

    protected $fillable = [
        'user_id',
        'event_key',
        'event_title',
        'status',
        'note',
        'responded_at',
    ];

    protected $casts = [
        'responded_at' => 'datetime',
    ];

    public function farmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_SAFE ? 'Safe' : 'Needs help';
    }
}
