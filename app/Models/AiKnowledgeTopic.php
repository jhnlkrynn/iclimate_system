<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiKnowledgeTopic extends Model
{
    use FlushesAiCache;

    protected $fillable = ['slug', 'category', 'title_en', 'title_fil', 'description', 'prefers_live_data', 'is_context', 'is_active'];

    protected function casts(): array
    {
        return ['prefers_live_data' => 'boolean', 'is_context' => 'boolean', 'is_active' => 'boolean'];
    }

    public function aiCacheGroup(): string
    {
        return 'vocab';
    }

    public function terms(): HasMany
    {
        return $this->hasMany(AiTerm::class, 'topic_id');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(AiFaq::class, 'topic_id');
    }
}
