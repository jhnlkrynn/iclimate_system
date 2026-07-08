<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}
