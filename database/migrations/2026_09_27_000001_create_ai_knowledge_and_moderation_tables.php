<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bilingual farming/weather vocabulary and the chatbot's language filter.
     * All of it is data, editable by IT Experts and MAO personnel; none of the
     * vocabulary lives in PHP. Additive: the existing knowledge_base table and
     * chat history are untouched.
     */
    public function up(): void
    {
        Schema::create('ai_knowledge_topics', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 60)->unique();          // canonical concept key, e.g. "harvest"
            $table->string('category', 30)->index();       // rice_farming | weather | general
            $table->string('title_en');
            $table->string('title_fil')->nullable();
            $table->text('description')->nullable();
            // A bare "what is the temperature?" usually wants live data, not a definition.
            $table->boolean('prefers_live_data')->default(false);
            // Names the crop or setting (e.g. "palay", "weather"): it never needs its own answer when a more specific topic is also named.
            $table->boolean('is_context')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ai_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->constrained('ai_knowledge_topics')->cascadeOnDelete();
            $table->string('category', 30)->index();
            $table->string('term_en');
            $table->string('term_fil')->nullable();
            $table->json('alt_spellings')->nullable();
            $table->text('definition_en')->nullable();
            $table->text('definition_fil')->nullable();
            $table->text('farming_context')->nullable();
            $table->json('related_terms')->nullable();
            $table->json('example_questions')->nullable();
            $table->text('approved_response_en')->nullable();
            $table->text('approved_response_fil')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ai_synonyms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('term_id')->constrained('ai_terms')->cascadeOnDelete();
            $table->string('synonym', 120);
            $table->string('language', 10)->default('any');   // any | en | fil | taglish
            $table->string('kind', 15)->default('synonym');   // synonym | spelling | abbreviation
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['term_id', 'synonym']);
        });

        Schema::create('ai_faqs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained('ai_knowledge_topics')->nullOnDelete();
            $table->string('category', 30)->default('rice_farming')->index();
            $table->string('question_en');
            $table->string('question_fil')->nullable();
            $table->text('answer_en');
            $table->text('answer_fil')->nullable();
            $table->json('keywords')->nullable();
            $table->string('source')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ai_blocked_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('term', 80);
            $table->string('normalized_term', 80)->unique();
            $table->string('language', 10)->default('any');
            $table->string('category', 20)->default('profanity'); // profanity | sexual | insult | slur | abusive
            // Phrases that make an occurrence harmless (e.g. "leche flan").
            $table->json('exceptions')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ai_blocked_patterns', function (Blueprint $table): void {
            $table->id();
            $table->string('pattern', 200);
            $table->string('description')->nullable();
            $table->string('language', 10)->default('any');
            $table->string('category', 20)->default('abusive');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        // Deliberately holds no message text, no matched word and no pattern.
        Schema::create('ai_moderation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('language', 40);
            $table->string('category', 20);
            $table->string('reason_code', 30);
            $table->boolean('blocked')->default(true);
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        foreach (['ai_moderation_events', 'ai_blocked_patterns', 'ai_blocked_terms', 'ai_faqs', 'ai_synonyms', 'ai_terms', 'ai_knowledge_topics'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
