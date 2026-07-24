<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeedPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'category',
        'visibility',
        'event_date',
        'show_on_calendar',
        'archived_at',
    ];

    protected $casts = [
        'event_date' => 'datetime',
        'show_on_calendar' => 'boolean',
        'archived_at' => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(FeedMedia::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(FeedComment::class);
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(FeedReaction::class);
    }
}
