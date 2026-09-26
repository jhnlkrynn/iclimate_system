<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSynonym extends Model
{
    use FlushesAiCache;

    protected $fillable = ['term_id', 'synonym', 'language', 'kind', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function aiCacheGroup(): string
    {
        return 'vocab';
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(AiTerm::class, 'term_id');
    }
}
