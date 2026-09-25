<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A safe record that a message was blocked. It never contains the message,
 * the word that matched, or the pattern that matched.
 */
class AiModerationEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'language', 'category', 'reason_code', 'blocked'];

    protected function casts(): array
    {
        return ['blocked' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
