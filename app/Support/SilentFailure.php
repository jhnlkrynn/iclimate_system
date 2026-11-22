<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Audit 2026-11-23 (M-3): a failure the code deliberately handles (a fallback value, a skipped figure, "keep the saved
 * data") still has to reach the log, or a real fault stays invisible. This writes one warning per place and error type
 * per hour (so a failure that repeats on every page load cannot flood the log), and changes nothing else: the caller
 * keeps returning what it returned before.
 *
 *   rescue(fn () => ..., $fallback, fn (\Throwable $e): bool => SilentFailure::log($e, 'Class::method'))
 *   catch (Throwable $e) { SilentFailure::log($e, 'Class::method'); ...the existing fallback... }
 *
 * Returns false, so as rescue()'s $report it also stops rescue() from calling report() a second time. Never throws.
 */
final class SilentFailure
{
    /** One warning per place and error type within this many seconds. */
    public const ONCE_PER_SECONDS = 3600;

    public static function log(Throwable $exception, string $where): bool
    {
        try {
            $key = 'silent-failure:'.md5($where.'|'.$exception::class);
            if (! Cache::add($key, true, self::ONCE_PER_SECONDS)) {
                return false;                                   // already logged within the hour
            }
        } catch (Throwable) {
            // The cache itself is failing: log anyway (better a repeat than nothing).
        }

        try {
            Log::warning('Handled failure (the app carried on with its fallback): '.$where, [
                'exception' => $exception::class,
                'message' => mb_substr($exception->getMessage(), 0, 500),
                'at' => $exception->getFile().':'.$exception->getLine(),
            ]);
        } catch (Throwable) {
            // Logging must never turn a handled failure into an unhandled one.
        }

        return false;
    }
}
