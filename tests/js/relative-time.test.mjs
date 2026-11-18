// node --test tests/js  (npm run test:js). Same table as tests/Unit/Support/RelativeTimeTest.php.
import test from 'node:test';
import assert from 'node:assert/strict';
import { formatRelative, formatRelativeSeconds } from '../../resources/js/relative-time.js';

const cases = [
    [0, 'en', 'just now'], [4, 'en', 'just now'], [-30, 'en', 'just now'],
    [5, 'en', '5 seconds ago'], [59, 'en', '59 seconds ago'],
    [60, 'en', '1 minute ago'], [125, 'en', '2 minutes ago'],
    [3600, 'en', '1 hour ago'], [7300, 'en', '2 hours ago'],
    [86400, 'en', '1 day ago'], [3 * 86400, 'en', '3 days ago'],
    [-120, 'en', 'in 2 minutes'],
    [2, 'fil', 'ngayon lang'], [45, 'fil', '45 segundo na ang nakalipas'], [60, 'fil', '1 minuto na ang nakalipas'],
];

for (const [seconds, locale, expected] of cases) {
    test(`${seconds}s (${locale}) → ${expected}`, () => {
        assert.equal(formatRelativeSeconds(seconds, locale), expected);
    });
}

test('advances with the clock, from the stored time (not from page load)', () => {
    const stored = '2026-11-11T12:00:00+00:00';
    const at = Date.parse(stored);
    assert.equal(formatRelative(stored, at + 5_000), '5 seconds ago');
    assert.equal(formatRelative(stored, at + 6_000), '6 seconds ago');
    assert.equal(formatRelative(stored, at + 120_000), '2 minutes ago');
});

test('a Manila-offset stored time reads the same as its UTC form', () => {
    const now = Date.parse('2026-11-11T12:00:30Z');
    assert.equal(formatRelative('2026-11-11T20:00:00+08:00', now), '30 seconds ago');
    assert.equal(formatRelative('2026-11-11T12:00:00Z', now), '30 seconds ago');
});

test('unreadable times are left alone', () => {
    assert.equal(formatRelative('', Date.now()), null);
    assert.equal(formatRelative('nope', Date.now()), null);
});

test('clock offset: a slow page render does not set the clock back; a wrong device clock is corrected', async () => {
    const { offsetFromWindow } = await import('../../resources/js/live-clock.js');
    // Server wrote its time 1.5 s into a 2 s render: same clocks → no correction.
    assert.equal(offsetFromWindow(1_500, 0, 2_000), 0);
    // Device clock 30 s behind the server: the server time is after the first byte → corrected by the gap.
    assert.equal(offsetFromWindow(32_000, 0, 2_000), 30_000);
    // Device clock 30 s ahead.
    assert.equal(offsetFromWindow(-30_000, 0, 2_000), -30_000);
});
