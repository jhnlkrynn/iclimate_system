<?php

namespace App\Services\Security;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CaptchaService
{
    public const SESSION_KEY = 'security.captcha';

    /**
     * Number of consecutive failed login attempts after which the CAPTCHA is shown.
     */
    public const FAILED_ATTEMPTS_THRESHOLD = 3;

    private const TTL_SECONDS = 600;

    private const CODE_LENGTH = 5;

    private const CODE_CHARACTERS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Kept out from under the "security.captcha" node: that key is replaced wholesale
     * every time the challenge rotates (Laravel session keys nest on dots), which would
     * wipe a sibling counter stored underneath it.
     */
    private const ATTEMPTS_SESSION_PREFIX = 'security.captcha_attempts.';

    /**
     * Return the current challenge for the context, or create a new one.
     *
     * @return array{context: string, token: string, created_at: int}
     */
    public function challenge(Request $request, string $context): array
    {
        $challenge = $request->session()->get(self::SESSION_KEY);

        if (! is_array($challenge) || ($challenge['context'] ?? null) !== $context || $this->isExpired($challenge)) {
            return $this->generate($request, $context);
        }

        return $this->publicChallenge($challenge);
    }

    /**
     * Validate the submitted answer and rotate the challenge after the attempt.
     */
    public function validate(Request $request, string $context, ?string $answer): bool
    {
        $challenge = $request->session()->get(self::SESSION_KEY);

        $isValid = is_array($challenge)
            && ($challenge['context'] ?? null) === $context
            && ! $this->isExpired($challenge)
            && isset($challenge['answer_hash'])
            && Hash::check(Str::upper(trim((string) $answer)), (string) $challenge['answer_hash']);

        $this->generate($request, $context);

        return $isValid;
    }

    /**
     * @return array{context: string, token: string, created_at: int}
     */
    public function generate(Request $request, string $context): array
    {
        $code = $this->randomCode();

        $challenge = [
            'context' => $context,
            'code' => $code,
            'answer_hash' => Hash::make($code),
            'token' => Str::random(16),
            'created_at' => now()->timestamp,
        ];

        $request->session()->put(self::SESSION_KEY, $challenge);

        return $this->publicChallenge($challenge);
    }

    public function svg(Request $request, string $context): string
    {
        $this->challenge($request, $context);

        $challenge = $request->session()->get(self::SESSION_KEY);
        $code = is_array($challenge) ? (string) ($challenge['code'] ?? '') : '';

        if ($code === '') {
            $this->generate($request, $context);
            $challenge = $request->session()->get(self::SESSION_KEY);
            $code = is_array($challenge) ? (string) ($challenge['code'] ?? '') : '';
        }

        $letters = str_split($code);
        $letterSvg = '';

        foreach ($letters as $index => $letter) {
            $x = 20 + ($index * 31) + random_int(-2, 2);
            $y = 45 + random_int(-5, 5);
            $rotation = random_int(-13, 13);
            $size = random_int(26, 31);
            $letterSvg .= sprintf(
                '<text x="%d" y="%d" transform="rotate(%d %d %d)" font-family="Inter, Arial, sans-serif" font-size="%d" font-weight="800" fill="#0f3d2e">%s</text>',
                $x,
                $y,
                $rotation,
                $x,
                $y,
                $size,
                e($letter)
            );
        }

        $noise = '';
        for ($i = 0; $i < 12; $i++) {
            $noise .= sprintf(
                '<line x1="%d" y1="%d" x2="%d" y2="%d" stroke="%s" stroke-width="%s" opacity=".55"/>',
                random_int(0, 190),
                random_int(0, 64),
                random_int(0, 190),
                random_int(0, 64),
                random_int(0, 1) === 1 ? '#52B788' : '#E8A73D',
                random_int(1, 2)
            );
        }

        for ($i = 0; $i < 34; $i++) {
            $noise .= sprintf(
                '<circle cx="%d" cy="%d" r="%d" fill="#1b5e45" opacity=".22"/>',
                random_int(0, 190),
                random_int(0, 64),
                random_int(1, 2)
            );
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="190" height="64" viewBox="0 0 190 64" role="img" aria-label="CAPTCHA code">
  <rect width="190" height="64" rx="13" fill="#eef8f2"/>
  <path d="M0 44 C34 20 62 70 104 37 S160 14 190 35" fill="none" stroke="#95D5B2" stroke-width="13" opacity=".28"/>
  {$noise}
  {$letterSvg}
</svg>
SVG;
    }

    /**
     * Determine whether the CAPTCHA should be shown for the given context, based on
     * the number of consecutive failed attempts recorded for it in the session.
     */
    public function requiresCaptcha(Request $request, string $context): bool
    {
        return $this->failedAttempts($request, $context) >= self::FAILED_ATTEMPTS_THRESHOLD;
    }

    public function failedAttempts(Request $request, string $context): int
    {
        return (int) $request->session()->get(self::ATTEMPTS_SESSION_PREFIX.$context, 0);
    }

    /**
     * The session key used to store the failed-attempt counter for a context.
     */
    public static function attemptsSessionKey(string $context): string
    {
        return self::ATTEMPTS_SESSION_PREFIX.$context;
    }

    /**
     * Record a failed attempt for the given context and return the updated count.
     */
    public function recordFailedAttempt(Request $request, string $context): int
    {
        $count = $this->failedAttempts($request, $context) + 1;

        $request->session()->put(self::ATTEMPTS_SESSION_PREFIX.$context, $count);

        return $count;
    }

    /**
     * Clear the failed-attempt counter for the given context (e.g. after a successful login).
     */
    public function resetAttempts(Request $request, string $context): void
    {
        $request->session()->forget(self::ATTEMPTS_SESSION_PREFIX.$context);
    }

    /**
     * @param  array<string, mixed>  $challenge
     */
    private function isExpired(array $challenge): bool
    {
        return (int) ($challenge['created_at'] ?? 0) < now()->subSeconds(self::TTL_SECONDS)->timestamp;
    }

    private function randomCode(): string
    {
        $code = '';
        $max = strlen(self::CODE_CHARACTERS) - 1;

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_CHARACTERS[random_int(0, $max)];
        }

        return $code;
    }

    /**
     * @param  array<string, mixed>  $challenge
     * @return array{context: string, token: string, created_at: int}
     */
    private function publicChallenge(array $challenge): array
    {
        return [
            'context' => (string) ($challenge['context'] ?? ''),
            'token' => (string) ($challenge['token'] ?? ''),
            'created_at' => (int) ($challenge['created_at'] ?? now()->timestamp),
        ];
    }
}
