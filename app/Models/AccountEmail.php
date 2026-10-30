<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One account email (approved, not approved, deleted, restored) and what became of it: App\Services\Accounts\AccountEmails. */
class AccountEmail extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NO_VALID_EMAIL = 'no_valid_email';

    protected $fillable = ['user_id', 'type', 'recipient', 'status', 'error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope(Scopes\HideDeletedUsers::class);
    }

    /** "Sent", "Failed", "Queued" or "No valid email" (the Last email line). */
    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_QUEUED => 'Queued',
            default => 'No valid email',
        };
    }
}
