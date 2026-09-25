<?php

namespace Database\Seeders;

use App\Models\AiFaq;
use App\Models\AiKnowledgeTopic;
use App\Models\AiSynonym;
use App\Models\AiTerm;
use Illuminate\Database\Seeder;

/**
 * Loads the starter farming/weather vocabulary from
 * database/seeders/data/ai_vocabulary.json into the database.
 *
 * Safe to run repeatedly: rows that already exist are left exactly as they
 * are, so edits made in the admin pages are never overwritten and only
 * missing entries are added.
 */
class AiVocabularySeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode((string) file_get_contents(database_path('seeders/data/ai_vocabulary.json')), true, flags: JSON_THROW_ON_ERROR);
        $source = (string) ($data['source'] ?? 'iClimate starter content');

        foreach ($data['topics'] as $row) {
            $topic = AiKnowledgeTopic::query()->firstOrCreate(['slug' => $row['slug']], [
                'category' => $row['category'],
                'title_en' => $row['title_en'],
                'title_fil' => $row['title_fil'] ?? null,
                'prefers_live_data' => (bool) ($row['prefers_live_data'] ?? false),
                'is_context' => (bool) ($row['is_context'] ?? false),
                'is_active' => true,
            ]);

            $term = AiTerm::query()->firstOrCreate(['topic_id' => $topic->id, 'term_en' => $row['title_en']], [
                'category' => $row['category'],
                'term_fil' => $row['title_fil'] ?? null,
                'alt_spellings' => $row['alt'] ?? [],
                'definition_en' => $row['def_en'],
                'definition_fil' => $row['def_fil'] ?? null,
                'related_terms' => $row['related'] ?? [],
                'example_questions' => $row['examples'] ?? [],
                'approved_response_en' => trim($row['def_en'].' '.($row['tip_en'] ?? '')),
                'approved_response_fil' => isset($row['def_fil']) ? trim($row['def_fil'].' '.($row['tip_fil'] ?? '')) : null,
                'farming_context' => $row['tip_en'] ?? null,
                'source' => $source,
                'is_active' => true,
            ]);

            foreach ($row['syn'] ?? [] as $entry) {
                [$language, $text] = str_contains($entry, ':') ? explode(':', $entry, 2) : ['any', $entry];

                AiSynonym::query()->firstOrCreate(['term_id' => $term->id, 'synonym' => $text], [
                    'language' => $language,
                    'kind' => $language === 'abbr' ? 'abbreviation' : 'synonym',
                    'is_active' => true,
                ]);
            }
        }

        foreach ($data['faqs'] as $row) {
            AiFaq::query()->firstOrCreate(['question_en' => $row['q_en']], [
                'topic_id' => AiKnowledgeTopic::query()->where('slug', $row['slug'])->value('id'),
                'category' => $row['category'],
                'question_fil' => $row['q_fil'] ?? null,
                'answer_en' => $row['a_en'],
                'answer_fil' => $row['a_fil'] ?? null,
                'keywords' => $row['keywords'] ?? [],
                'source' => $source,
                'is_active' => true,
            ]);
        }
    }
}
