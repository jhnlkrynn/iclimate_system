<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiTerm extends Model
{
    use FlushesAiCache;

    protected $fillable = [
        'topic_id', 'category', 'term_en', 'term_fil', 'alt_spellings', 'definition_en', 'definition_fil',
        'farming_context', 'related_terms', 'example_questions', 'approved_response_en', 'approved_response_fil',
        'source', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'alt_spellings' => 'array',
            'related_terms' => 'array',
            'example_questions' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function aiCacheGroup(): string
    {
        return 'vocab';
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeTopic::class, 'topic_id');
    }

    public function synonyms(): HasMany
    {
        return $this->hasMany(AiSynonym::class, 'term_id');
    }
}
