<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class FeedMedia extends Model
{
    protected $table = 'feed_media';

    protected $fillable = [
        'feed_post_id',
        'path',
        'original_name',
        'mime_type',
        'media_type',
        'size',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(FeedPost::class, 'feed_post_id');
    }

    public function existsOnDisk(): bool
    {
        return filled($this->path) && Storage::disk('public')->exists($this->path);
    }

    public function url(): ?string
    {
        return $this->existsOnDisk() ? Storage::disk('public')->url($this->path) : null;
    }
}
