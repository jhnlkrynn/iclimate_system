<?php

namespace Tests\Unit\Support;

use App\Support\RelativeTime;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Same table as tests/js/relative-time.test.mjs: the server's first render and the browser's ticking text agree. */
class RelativeTimeTest extends TestCase
{
    public static function cases(): array
    {
        return [
            [0, 'en', 'just now'], [4, 'en', 'just now'], [-30, 'en', 'just now'],
            [5, 'en', '5 seconds ago'], [59, 'en', '59 seconds ago'],
            [60, 'en', '1 minute ago'], [125, 'en', '2 minutes ago'],
            [3600, 'en', '1 hour ago'], [7300, 'en', '2 hours ago'],
            [86400, 'en', '1 day ago'], [3 * 86400, 'en', '3 days ago'],
            [-120, 'en', 'in 2 minutes'],
            [2, 'fil', 'ngayon lang'], [45, 'fil', '45 segundo na ang nakalipas'], [60, 'fil', '1 minuto na ang nakalipas'],
        ];
    }

    #[DataProvider('cases')]
    public function test_wording(int $seconds, string $locale, string $expected): void
    {
        $this->assertSame($expected, RelativeTime::fromSeconds($seconds, $locale));
    }

    public function test_counts_from_the_stored_time_whatever_its_zone(): void
    {
        $now = Carbon::parse('2026-11-11 12:00:30', 'UTC');

        // The same instant written as Manila wall clock and as UTC reads the same age.
        $this->assertSame('30 seconds ago', RelativeTime::format(Carbon::parse('2026-11-11 20:00:00', 'Asia/Manila'), $now, 'en'));
        $this->assertSame('30 seconds ago', RelativeTime::format('2026-11-11T12:00:00+00:00', $now, 'en'));
        $this->assertNull(RelativeTime::format(null, $now));
        $this->assertNull(RelativeTime::format('not a time', $now));
    }
}
