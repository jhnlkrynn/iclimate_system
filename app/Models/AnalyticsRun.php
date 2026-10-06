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

    /** Start of the stored error when a run was given up on because no worker finished it in time. */
    public const TIMEOUT_ERROR = 'Timed out';

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

    /** Whether this run has waited (or been running) longer than a background worker should ever need. */
    public function isStale(): bool
    {
        return match ($this->status) {
            self::STATUS_PENDING => $this->created_at !== null
                && $this->created_at->lt(now()->subSeconds((int) config('services.farming_ai.pending_timeout_seconds', 90))),
            self::STATUS_PROCESSING => ($this->started_at ?? $this->created_at) !== null
                && ($this->started_at ?? $this->created_at)->lt(now()->subSeconds((int) config('services.farming_ai.processing_timeout_seconds', 300))),
            default => false,
        };
    }

    /**
     * Give up on a run nothing finished in time, so the page that is waiting for it stops loading and can say
     * "try again" instead of spinning forever (this happens when the queue worker is not running).
     */
    public function expireIfStale(): bool
    {
        if (! $this->isStale()) {
            return false;
        }

        $waited = $this->status === self::STATUS_PENDING ? 'waiting for the queue worker' : 'being processed';
        // Only while it is still unfinished: a worker may have completed it a moment ago.
        $expired = static::query()->whereKey($this->getKey())
            ->whereIn('status', [self::STATUS_PENDING, self::STATUS_PROCESSING])
            ->update([
                'status' => self::STATUS_FAILED,
                'error' => self::TIMEOUT_ERROR.' while '.$waited.'. Check that the queue worker is running (php artisan queue:work).',
                'completed_at' => now(),
            ]);
        $this->refresh();

        return $expired > 0;
    }

    public function timedOut(): bool
    {
        return $this->status === self::STATUS_FAILED && str_starts_with((string) $this->error, self::TIMEOUT_ERROR);
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
