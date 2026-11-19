<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmerAccountReview extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['application' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Account reviews are append-only.'));
        static::deleting(fn () => throw new \LogicException('Account reviews are append-only.'));
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id')->withoutGlobalScope(\App\Models\Scopes\HideDeletedUsers::class);
    }
}
