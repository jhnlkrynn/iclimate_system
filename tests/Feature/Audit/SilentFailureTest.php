<?php

namespace Tests\Feature\Audit;

use App\Support\SilentFailure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Audit 2026-11-23 (M-3): handled failures reach the log, once per place and error type per hour, and the caller's
 * result (the fallback the user sees) is exactly what it was before.
 */
class SilentFailureTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_a_handled_failure_is_logged_once_per_hour_per_place(): void
    {
        Log::spy();

        foreach (range(1, 5) as $_) {
            SilentFailure::log(new RuntimeException('database gone'), 'Example::page');
        }
        SilentFailure::log(new RuntimeException('database gone'), 'Example::other');

        Log::shouldHaveReceived('warning')->twice();
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => str_contains($message, 'Example::page')
            && $context['exception'] === RuntimeException::class && $context['message'] === 'database gone');
    }

    public function test_rescue_keeps_its_fallback_and_does_not_report_a_second_time(): void
    {
        Log::spy();

        $value = rescue(fn () => throw new RuntimeException('boom'), 'fallback', fn (\Throwable $e): bool => SilentFailure::log($e, 'Example::value'));

        $this->assertSame('fallback', $value);
        Log::shouldHaveReceived('warning')->once();
        Log::shouldNotHaveReceived('error');
    }

    public function test_it_never_throws_even_when_the_cache_and_the_log_fail(): void
    {
        Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));
        Log::shouldReceive('warning')->andThrow(new RuntimeException('log down'));

        $this->assertFalse(SilentFailure::log(new RuntimeException('x'), 'Example::broken'));
    }
}
