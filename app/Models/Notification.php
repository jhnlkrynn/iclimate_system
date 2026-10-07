<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    /** Set on a warning about weather so the farmer dashboard can show it as a weather alert. */
    public const CATEGORY_WEATHER = 'weather';

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'category',
        'is_read',
        // Manuscript – Naicel (2026-10-08): when it was read, and the advisory an alert is about.
        'read_at',
        'planting_advisory_id',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',   // Manuscript – Naicel
    ];

    /** Manuscript – Naicel (2026-10-08): read_at follows is_read on every save (bulk updates set it themselves). */
    protected static function booted(): void
    {
        static::saving(function (self $notification): void {
            if (! \App\Support\SchemaCache::hasColumn('notifications', 'read_at')) {
                return;
            }
            if ($notification->is_read && $notification->read_at === null) {
                $notification->read_at = now();
            } elseif (! $notification->is_read) {
                $notification->read_at = null;
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
