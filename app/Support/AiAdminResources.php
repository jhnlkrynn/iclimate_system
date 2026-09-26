<?php

namespace App\Support;

use App\Models\AiBlockedPattern;
use App\Models\AiBlockedTerm;
use App\Models\AiFaq;
use App\Models\AiKnowledgeTopic;
use App\Models\AiSynonym;
use App\Models\AiTerm;
use App\Services\AI\TextNormalizer;
use Closure;

/**
 * Describes the chatbot data IT Experts and MAO officers can manage: which
 * model backs each page, its editable fields, validation, and the columns
 * shown in the list. One generic controller and view are driven by this map,
 * so adding a field here is all it takes to make it editable.
 *
 * Field types: text, textarea, list (one item per line, stored as an array),
 * select, bool.
 */
class AiAdminResources
{
    private const CATEGORIES = ['rice_farming' => 'Rice farming', 'weather' => 'Weather', 'general' => 'General'];

    private const LANGUAGES = ['any' => 'Any', 'en' => 'English', 'fil' => 'Filipino', 'taglish' => 'Taglish'];

    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        return [
            'topics' => [
                'label' => 'Topics',
                'help' => 'A topic is one idea (for example "harvest"). Every word and synonym for it points here, so all of them are understood as the same thing.',
                'model' => AiKnowledgeTopic::class,
                'columns' => ['slug', 'category', 'title_en', 'title_fil'],
                'search' => ['slug', 'title_en', 'title_fil'],
                'fields' => [
                    'slug' => ['type' => 'text', 'label' => 'Concept key (lowercase, e.g. harvest)', 'rules' => ['required', 'string', 'max:60', 'regex:/^[a-z0-9_]+$/'], 'unique' => true],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => self::CATEGORIES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))]],
                    'title_en' => ['type' => 'text', 'label' => 'English name', 'rules' => ['required', 'string', 'max:255']],
                    'title_fil' => ['type' => 'text', 'label' => 'Filipino name', 'rules' => ['nullable', 'string', 'max:255']],
                    'description' => ['type' => 'textarea', 'label' => 'Notes', 'rules' => ['nullable', 'string', 'max:2000']],
                    'prefers_live_data' => ['type' => 'bool', 'label' => 'Questions with this word usually want live data (weather, temperature...)'],
                    'is_context' => ['type' => 'bool', 'label' => 'Generic word (crop or setting), e.g. palay, weather'],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
            ],
            'terms' => [
                'label' => 'Terms',
                'help' => 'Vocabulary entries with definitions and the approved answer the chatbot gives. The chatbot only says what is written here.',
                'model' => AiTerm::class,
                'columns' => ['term_en', 'term_fil', 'category', 'topic.slug'],
                'search' => ['term_en', 'term_fil'],
                'with' => ['topic'],
                'fields' => [
                    'topic_id' => ['type' => 'select', 'label' => 'Topic', 'options' => fn () => AiKnowledgeTopic::query()->orderBy('slug')->pluck('slug', 'id')->all(), 'rules' => ['required', 'exists:ai_knowledge_topics,id']],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => self::CATEGORIES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))]],
                    'term_en' => ['type' => 'text', 'label' => 'English term', 'rules' => ['required', 'string', 'max:255']],
                    'term_fil' => ['type' => 'text', 'label' => 'Filipino term', 'rules' => ['nullable', 'string', 'max:255']],
                    'alt_spellings' => ['type' => 'list', 'label' => 'Alternative spellings / local words (one per line)', 'rules' => ['nullable', 'array'], 'item_rules' => ['string', 'max:120']],
                    'definition_en' => ['type' => 'textarea', 'label' => 'Definition (English)', 'rules' => ['nullable', 'string', 'max:2000']],
                    'definition_fil' => ['type' => 'textarea', 'label' => 'Definition (Filipino)', 'rules' => ['nullable', 'string', 'max:2000']],
                    'farming_context' => ['type' => 'textarea', 'label' => 'Farming context', 'rules' => ['nullable', 'string', 'max:2000']],
                    'related_terms' => ['type' => 'list', 'label' => 'Related terms (one per line)', 'rules' => ['nullable', 'array'], 'item_rules' => ['string', 'max:120']],
                    'example_questions' => ['type' => 'list', 'label' => 'Example questions (one per line)', 'rules' => ['nullable', 'array'], 'item_rules' => ['string', 'max:255']],
                    'approved_response_en' => ['type' => 'textarea', 'label' => 'Approved answer (English)', 'rules' => ['nullable', 'string', 'max:3000']],
                    'approved_response_fil' => ['type' => 'textarea', 'label' => 'Approved answer (Filipino)', 'rules' => ['nullable', 'string', 'max:3000']],
                    'source' => ['type' => 'text', 'label' => 'Source / reference', 'rules' => ['nullable', 'string', 'max:255']],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
            ],
            'synonyms' => [
                'label' => 'Synonyms',
                'help' => 'Another way to say a term, an alternative spelling, or an abbreviation.',
                'model' => AiSynonym::class,
                'columns' => ['synonym', 'language', 'kind', 'term.term_en'],
                'search' => ['synonym'],
                'with' => ['term'],
                'fields' => [
                    'term_id' => ['type' => 'select', 'label' => 'Term', 'options' => fn () => AiTerm::query()->orderBy('term_en')->pluck('term_en', 'id')->all(), 'rules' => ['required', 'exists:ai_terms,id']],
                    'synonym' => ['type' => 'text', 'label' => 'Word or phrase', 'rules' => ['required', 'string', 'max:120']],
                    'language' => ['type' => 'select', 'label' => 'Language', 'options' => self::LANGUAGES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::LANGUAGES))]],
                    'kind' => ['type' => 'select', 'label' => 'Kind', 'options' => ['synonym' => 'Synonym', 'spelling' => 'Spelling variation', 'abbreviation' => 'Abbreviation'], 'rules' => ['required', 'in:synonym,spelling,abbreviation']],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
            ],
            'faqs' => [
                'label' => 'FAQs',
                'help' => 'Approved question-and-answer pairs, such as how-to questions.',
                'model' => AiFaq::class,
                'columns' => ['question_en', 'question_fil', 'category'],
                'search' => ['question_en', 'question_fil'],
                'fields' => [
                    'topic_id' => ['type' => 'select', 'label' => 'Topic (optional)', 'options' => fn () => AiKnowledgeTopic::query()->orderBy('slug')->pluck('slug', 'id')->all(), 'rules' => ['nullable', 'exists:ai_knowledge_topics,id']],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => self::CATEGORIES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))]],
                    'question_en' => ['type' => 'text', 'label' => 'Question (English)', 'rules' => ['required', 'string', 'max:255']],
                    'question_fil' => ['type' => 'text', 'label' => 'Question (Filipino)', 'rules' => ['nullable', 'string', 'max:255']],
                    'answer_en' => ['type' => 'textarea', 'label' => 'Approved answer (English)', 'rules' => ['required', 'string', 'max:3000']],
                    'answer_fil' => ['type' => 'textarea', 'label' => 'Approved answer (Filipino)', 'rules' => ['nullable', 'string', 'max:3000']],
                    'keywords' => ['type' => 'list', 'label' => 'Keywords (one per line)', 'rules' => ['nullable', 'array'], 'item_rules' => ['string', 'max:120']],
                    'source' => ['type' => 'text', 'label' => 'Source / reference', 'rules' => ['nullable', 'string', 'max:255']],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
            ],
            'blocked-terms' => [
                'label' => 'Blocked terms',
                'help' => 'Words and phrases the chatbot refuses. Matched as whole words after normalizing spelling tricks, so harmless words that merely contain them are not blocked. Use "exceptions" for phrases where a word is harmless.',
                'sensitive' => true,
                'model' => AiBlockedTerm::class,
                'columns' => ['term', 'language', 'category'],
                'search' => ['term'],
                'log_content' => false,
                'fields' => [
                    'term' => ['type' => 'text', 'label' => 'Word or phrase', 'rules' => ['required', 'string', 'max:80']],
                    'language' => ['type' => 'select', 'label' => 'Language', 'options' => self::LANGUAGES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::LANGUAGES))]],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => ['profanity' => 'Profanity', 'sexual' => 'Sexual / obscene', 'insult' => 'Insult', 'slur' => 'Slur', 'abusive' => 'Abusive'], 'rules' => ['required', 'in:profanity,sexual,insult,slur,abusive']],
                    'exceptions' => ['type' => 'list', 'label' => 'Harmless phrases containing it (one per line, e.g. leche flan)', 'rules' => ['nullable', 'array'], 'item_rules' => ['string', 'max:80']],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
                'validate' => function (array $data, ?int $ignoreId): ?string {
                    $normalized = app(TextNormalizer::class)->term((string) $data['term']);

                    if ($normalized === '') {
                        return 'The word must contain letters or numbers.';
                    }

                    return AiBlockedTerm::query()->where('normalized_term', $normalized)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()
                        ? 'That word or phrase is already in the list.'
                        : null;
                },
            ],
            'blocked-patterns' => [
                'label' => 'Blocked patterns',
                'help' => 'Regular expressions for abuse that depends on context (for example "lintik ka"). They run against the lowercased message. Keep them specific.',
                'sensitive' => true,
                'model' => AiBlockedPattern::class,
                'columns' => ['pattern', 'category', 'description'],
                'search' => ['pattern', 'description'],
                'log_content' => false,
                'fields' => [
                    'pattern' => ['type' => 'text', 'label' => 'Pattern (regular expression)', 'rules' => ['required', 'string', 'max:200']],
                    'description' => ['type' => 'text', 'label' => 'What it catches', 'rules' => ['nullable', 'string', 'max:255']],
                    'language' => ['type' => 'select', 'label' => 'Language', 'options' => self::LANGUAGES, 'rules' => ['required', 'in:'.implode(',', array_keys(self::LANGUAGES))]],
                    'category' => ['type' => 'select', 'label' => 'Category', 'options' => ['profanity' => 'Profanity', 'sexual' => 'Sexual / obscene', 'insult' => 'Insult', 'slur' => 'Slur', 'abusive' => 'Abusive'], 'rules' => ['required', 'in:profanity,sexual,insult,slur,abusive']],
                    'is_active' => ['type' => 'bool', 'label' => 'Active'],
                ],
                'validate' => fn (array $data, ?int $ignoreId): ?string => AiBlockedPattern::problemWith((string) $data['pattern']),
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    public static function find(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Resolve a select's options, which may be computed from the database. */
    public static function options(array $field): array
    {
        $options = $field['options'] ?? [];

        return $options instanceof Closure ? $options() : $options;
    }
}
