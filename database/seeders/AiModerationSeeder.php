<?php

namespace Database\Seeders;

use App\Models\AiBlockedPattern;
use App\Models\AiBlockedTerm;
use App\Services\AI\TextNormalizer;
use Illuminate\Database\Seeder;

/**
 * Loads the starter language-filter dictionary from
 * database/seeders/data/ai_moderation.json. It is a starting point, not a
 * complete list: IT Experts and MAO add terms and patterns over time in the
 * admin pages. Existing rows are never overwritten, so a term an admin turned
 * off stays off.
 */
class AiModerationSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode((string) file_get_contents(database_path('seeders/data/ai_moderation.json')), true, flags: JSON_THROW_ON_ERROR);
        $normalizer = app(TextNormalizer::class);

        foreach ($data['terms'] as $row) {
            if (AiBlockedTerm::query()->where('normalized_term', $normalizer->term($row['term']))->exists()) {
                continue;
            }

            AiBlockedTerm::query()->create([
                'term' => $row['term'],
                'language' => $row['language'] ?? 'any',
                'category' => $row['category'] ?? 'profanity',
                'exceptions' => $row['exceptions'] ?? [],
                'is_active' => true,
            ]);
        }

        foreach ($data['patterns'] as $row) {
            AiBlockedPattern::query()->firstOrCreate(['pattern' => $row['pattern']], [
                'description' => $row['description'] ?? null,
                'language' => $row['language'] ?? 'any',
                'category' => $row['category'] ?? 'abusive',
                'is_active' => true,
            ]);
        }
    }
}
