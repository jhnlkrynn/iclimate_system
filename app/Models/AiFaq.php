<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiFaq extends Model
{
    use FlushesAiCache;

    protected $fillable = [
        'topic_id', 'category', 'question_en', 'question_fil', 'answer_en', 'answer_fil', 'keywords', 'source', 'is_active',
    ];

    protected function casts(): array
    {
        return ['keywords' => 'array', 'is_active' => 'boolean'];
    }

    public function aiCacheGroup(): string
    {
        return 'vocab';
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeTopic::class, 'topic_id');
    }
}
