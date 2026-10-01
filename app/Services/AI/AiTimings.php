<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;

/**
 * Per-request stopwatch for the chatbot: how long each stage took and how much
 * of the response budget is left.
 *
 * It records stage names and milliseconds only. Message text, user data,
 * tokens and provider responses never pass through here, so the log line it
 * writes is safe to keep. Outside a chat request (queue workers, commands) it
 * is inactive: nothing is recorded and the full budget is reported as left.
 */
class AiTimings
{
    /** @var array<string, float> milliseconds per stage */
    private array $stages = [];

    private float $startedAt;

    private int $groqCalls = 0;

    private bool $active = false;

    private float $dbMs = 0.0;

    private int $dbQueries = 0;

    private string $intent = 'unknown';

    private string $path = 'system';

    public function __construct()
    {
        $this->startedAt = microtime(true);
    }

    /** Start a fresh measurement (called at the beginning of every chat request). */
    public function begin(): void
    {
        $this->stages = [];
        $this->groqCalls = 0;
        $this->dbMs = 0.0;
        $this->dbQueries = 0;
        $this->intent = 'unknown';
        $this->path = 'system';
        $this->startedAt = microtime(true);
        $this->active = true;
    }

    public function isActive(): bool
    {
        return $this->active;
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
        if (! $this->active) {
            return;
        }

        $this->stages[$stage] = ($this->stages[$stage] ?? 0) + $milliseconds;

        if ($stage === 'groq') {
            $this->groqCalls++;
        }
    }

    /** Called for every database query; adds up how much of the request was spent waiting on Supabase. */
    public function recordQuery(float $milliseconds): void
    {
        if ($this->active) {
            $this->dbMs += $milliseconds;
            $this->dbQueries++;
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

    /** Seconds left in the whole-response budget (never negative). Outside a chat request the whole budget is left. */
    public function remainingSeconds(): float
    {
        $budget = (float) config('ai.response_budget_seconds', 10);

        return $this->active ? max(0.0, $budget - (microtime(true) - $this->startedAt)) : $budget;
    }

    /**
     * @return array<string, int>
     */
    public function stages(): array
    {
        return array_map(fn (float $ms): int => (int) round($ms), $this->stages);
    }

    /** Remembers which route answered, so whoever finishes the request can log it. */
    public function route(string $intent, string $path): void
    {
        $this->intent = $intent;
        $this->path = $path;
    }

    /** One log line per request: stage times, database time, the route taken and the total. Ends the measurement. */
    public function report(?string $intent = null, ?string $path = null): void
    {
        if ($intent !== null || $path !== null) {
            $this->route($intent ?? $this->intent, $path ?? $this->path);
        }

        Log::info('AI response timing', [
            'intent' => $this->intent,
            'path' => $this->path,
            'groq_calls' => $this->groqCalls,
            'stages_ms' => $this->stages(),
            'db_ms' => (int) round($this->dbMs),
            'db_queries' => $this->dbQueries,
            'total_ms' => $this->elapsedMs(),
        ]);

        $this->active = false;
    }
}
