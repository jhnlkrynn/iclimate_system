<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AnalyticsRun extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'uuid', 'type', 'status', 'requested_by', 'cache_key', 'input', 'result',
        'error', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'input' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $run): void {
            $run->uuid ??= (string) Str::uuid();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
