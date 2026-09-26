<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Per-request stopwatch for the chatbot: how long each stage took and how much
 * of the response budget is left.
 *
 * It records stage names and milliseconds only. Message text, user data,
 * tokens and provider responses never pass through here, so the log line it
 * writes is safe to keep.
 */
class AiTimings
{
    /** @var array<string, float> milliseconds per stage */
    private array $stages = [];

    private float $startedAt;

    private int $groqCalls = 0;

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    /** Start a fresh measurement (called at the beginning of every chat message). */
    public function begin(): void
    {
        $this->stages = [];
        $this->groqCalls = 0;
        $this->startedAt = microtime(true);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function time(string $stage, callable $work): mixed
    {
        $started = microtime(true);

        try {
            return $work();
        } finally {
            $this->add($stage, (microtime(true) - $started) * 1000);
        }
    }

    public function add(string $stage, float $milliseconds): void
    {
        $this->stages[$stage] = ($this->stages[$stage] ?? 0) + $milliseconds;

        if ($stage === 'groq') {
            $this->groqCalls++;
        }
    }

    public function groqCalls(): int
    {
        return $this->groqCalls;
    }

    public function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }

    /** Seconds left in the whole-response budget (never negative). */
    public function remainingSeconds(): float
    {
        return max(0.0, (float) config('ai.response_budget_seconds', 10) - (microtime(true) - $this->startedAt));
    }

    /**
     * @return array<string, int>
     */
    public function stages(): array
    {
        return array_map(fn (float $ms): int => (int) round($ms), $this->stages);
    }

    /** One log line per message: stage times, the route taken and the total. */
    public function report(string $intent, string $path): void
    {
        Log::info('AI response timing', [
            'intent' => $intent,
            'path' => $path,
            'groq_calls' => $this->groqCalls,
            'stages_ms' => $this->stages(),
            'total_ms' => $this->elapsedMs(),
        ]);
    }
}
