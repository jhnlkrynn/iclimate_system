<?php

namespace App\Models;

use App\Models\Concerns\FlushesAiCache;
use App\Services\AI\TextNormalizer;
use Illuminate\Database\Eloquent\Model;

class AiBlockedTerm extends Model
{
    use FlushesAiCache;

    protected $fillable = ['term', 'normalized_term', 'language', 'category', 'exceptions', 'is_active'];

    protected function casts(): array
    {
        return ['exceptions' => 'array', 'is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The matching form is always derived from what was typed, so an
        // admin cannot save a term the matcher will not understand.
        static::saving(function (AiBlockedTerm $term): void {
            $normalizer = app(TextNormalizer::class);
            $term->normalized_term = $normalizer->term((string) $term->term);
            $term->exceptions = array_values(array_filter(array_map(
                fn ($phrase) => $normalizer->term((string) $phrase),
                (array) $term->exceptions,
            )));
        });
    }

    public function aiCacheGroup(): string
    {
        return 'moderation';
    }
}
