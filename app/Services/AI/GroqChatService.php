<?php

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Throwable;

class GroqChatService
{
    public function available(): bool
    {
        return (bool) config('services.groq.enabled')
            && filled(config('services.groq.key'));
    }

    public function answer(User $user, string $question, string $intent, string $language, array $memory = []): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $model = (string) config('services.groq.model', 'llama-3.3-70b-versatile');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        try {
            $response = Http::withToken((string) config('services.groq.key'))
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 12))
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.35,
                    'max_completion_tokens' => 500,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($language),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->userPrompt($user, $question, $intent, $language, $memory),
                        ],
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $answer = trim((string) $response->json('choices.0.message.content'));

        if ($answer === '') {
            return null;
        }

        return [
            'answer' => $answer,
            'source_name' => 'Groq '.$model,
            'confidence' => 72,
            'model' => $model,
            'usage' => $response->json('usage'),
        ];
    }

    public function predictionFallback(User $user, string $question, string $intent, string $language, array $memory, array $predictionContext): ?array
    {
        if (! $this->available()) {
            return null;
        }

        $model = (string) config('services.groq.model', 'llama-3.3-70b-versatile');
        $baseUrl = rtrim((string) config('services.groq.base_url', 'https://api.groq.com/openai/v1'), '/');

        try {
            $response = Http::withToken((string) config('services.groq.key'))
                ->acceptJson()
                ->timeout((int) config('services.groq.timeout', 12))
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.25,
                    'max_completion_tokens' => 520,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->predictionFallbackSystemPrompt($language),
                        ],
                        [
                            'role' => 'user',
                            'content' => $this->predictionFallbackUserPrompt($user, $question, $intent, $language, $memory, $predictionContext),
                        ],
                    ],
                ]);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $answer = trim((string) $response->json('choices.0.message.content'));

        if ($answer === '') {
            return null;
        }

        return [
            'answer' => $answer,
            'source_name' => 'Groq '.$model,
            'confidence' => 70,
            'model' => $model,
            'usage' => $response->json('usage'),
        ];
    }

    private function systemPrompt(string $language): string
    {
        $languageInstruction = str_contains($language, 'Tagalog')
            ? 'Answer in natural Tagalog or mixed Tagalog-English when appropriate.'
            : 'Answer in clear English.';

        return <<<PROMPT
You are the iClimate Farming Assistant for rice farmers and agricultural staff in Lian, Batangas.
{$languageInstruction}
Your main specialty is iClimate and rice-agriculture topics: weather awareness, rice farming, planting, irrigation, fertilizer, soil, pests, disease, climate risk, advisories, announcements, profiles, and system help.
You may also answer harmless general knowledge, school, science, math, and everyday questions when the user asks them.
Do not invent private database records, account details, exact weather forecasts, or prediction values. If exact prediction values are needed, tell the user to use iClimate prediction features.
Keep answers practical, concise, and farmer-friendly. Do not include labels like Answer, Source, Confidence, Explanation, Recommendation, or Warning.
PROMPT;
    }

    private function predictionFallbackSystemPrompt(string $language): string
    {
        $languageInstruction = str_contains($language, 'Tagalog')
            ? 'Answer in natural Tagalog or mixed Tagalog-English when appropriate.'
            : 'Answer in clear English.';

        return <<<PROMPT
You are PalayPilot, the iClimate rice guidance assistant for Lian, Batangas.
{$languageInstruction}
The trained Python model or saved iClimate knowledge did not fully answer the user, so you are helping explain iClimate's already-computed fallback result.
Use only the supplied structured values for weather, yield, risk, planting, irrigation, warnings, and reliability.
Do not invent, change, or improve numeric prediction values. Do not claim live weather access.
Keep the answer concise, farmer-friendly, and practical. Mention when the result came from fallback rules if the context says so.
Do not include labels like Source or Confidence.
PROMPT;
    }

    private function userPrompt(User $user, string $question, string $intent, string $language, array $memory): string
    {
        $recentQuestions = implode(' | ', array_slice((array) ($memory['recent_questions'] ?? []), 0, 3));
        $barangay = $user->barangay ?: 'Unknown';

        return <<<PROMPT
User role: {$user->role}
User barangay: {$barangay}
Detected intent: {$intent}
Detected language: {$language}
Recent questions: {$recentQuestions}

Question:
{$question}
PROMPT;
    }

    private function predictionFallbackUserPrompt(User $user, string $question, string $intent, string $language, array $memory, array $predictionContext): string
    {
        $recentQuestions = implode(' | ', array_slice((array) ($memory['recent_questions'] ?? []), 0, 3));
        $barangay = $user->barangay ?: 'Unknown';
        $contextJson = json_encode($predictionContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return <<<PROMPT
User role: {$user->role}
User barangay: {$barangay}
Detected intent: {$intent}
Detected language: {$language}
Recent questions: {$recentQuestions}

Question:
{$question}

iClimate structured fallback result:
{$contextJson}

Write the best final answer for the user using those values only.
PROMPT;
    }
}
