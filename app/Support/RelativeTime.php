<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * "5 seconds ago" / "2 minutes ago" for a stored time, as first rendered by the server.
 *
 * resources/js/relative-time.js uses the same rules and keeps the text current every second in the browser,
 * so the two must stay in step (tests/Unit/RelativeTimeTest.php and tests/js/relative-time.test.mjs).
 */
final class RelativeTime
{
    private const WORDS = [
        'en' => ['now' => 'just now', 'second' => ['second', 'seconds'], 'minute' => ['minute', 'minutes'], 'hour' => ['hour', 'hours'], 'day' => ['day', 'days'], 'past' => ':n :unit ago', 'future' => 'in :n :unit'],
        'fil' => ['now' => 'ngayon lang', 'second' => ['segundo', 'segundo'], 'minute' => ['minuto', 'minuto'], 'hour' => ['oras', 'oras'], 'day' => ['araw', 'araw'], 'past' => ':n :unit na ang nakalipas', 'future' => 'sa loob ng :n :unit'],
    ];

    public static function format(CarbonInterface|string|null $at, ?CarbonInterface $now = null, ?string $locale = null): ?string
    {
        if ($at === null || $at === '') {
            return null;
        }

        $time = $at instanceof CarbonInterface ? $at : rescue(fn () => Carbon::parse($at), null, fn (\Throwable $e): bool => \App\Support\SilentFailure::log($e, 'RelativeTime::format'));
        if ($time === null) {
            return null;
        }

        $seconds = (int) floor(($now ?? Carbon::now())->getTimestamp() - $time->getTimestamp());

        return self::fromSeconds($seconds, $locale ?? app()->getLocale());
    }

    public static function fromSeconds(int $seconds, string $locale = 'en'): string
    {
        $words = self::WORDS[str_starts_with(strtolower($locale), 'fil') || str_starts_with(strtolower($locale), 'tl') ? 'fil' : 'en'];

        // Small clock differences between the server and the database must not read as "in 2 seconds".
        if ($seconds > -60 && $seconds < 5) {
            return $words['now'];
        }

        $future = $seconds < 0;
        $abs = abs($seconds);
        [$n, $unit] = match (true) {
            $abs < 60 => [$abs, 'second'],
            $abs < 3600 => [intdiv($abs, 60), 'minute'],
            $abs < 86400 => [intdiv($abs, 3600), 'hour'],
            default => [intdiv($abs, 86400), 'day'],
        };

        return strtr($words[$future ? 'future' : 'past'], [':n' => (string) $n, ':unit' => $words[$unit][$n === 1 ? 0 : 1]]);
    }
}
