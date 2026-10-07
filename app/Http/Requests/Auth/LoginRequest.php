<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Emails are stored lowercase, so a stray space or capital letter must not fail a valid sign-in.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => Str::lower(trim($this->input('email')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $requiresCaptcha = app(CaptchaService::class)->requiresCaptcha($this, 'login');

        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'captcha_answer' => [$requiresCaptcha ? 'required' : 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom validation messages for login.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'captcha_answer.required' => 'Please answer the security check before logging in.',
            'captcha_answer.max' => 'The security check answer is too long.',
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $captcha = app(CaptchaService::class);
        $requiresCaptcha = $captcha->requiresCaptcha($this, 'login');

        if ($requiresCaptcha && ! $captcha->validate($this, 'login', $this->input('captcha_answer'))) {
            RateLimiter::hit($this->throttleKey(), self::lockoutSeconds());
            $captcha->recordFailedAttempt($this, 'login');
            $this->auditFailure('captcha');

            throw ValidationException::withMessages([
                'captcha_answer' => 'The security check answer is incorrect. Please try again.',
            ]);
        }

        try {
            $authenticated = Auth::attempt($this->only('email', 'password'), $this->boolean('remember'));
        } catch (RuntimeException) {
            $authenticated = false;
        }

        if (! $authenticated) {
            RateLimiter::hit($this->throttleKey(), self::lockoutSeconds());
            $captcha->recordFailedAttempt($this, 'login');
            $this->auditFailure('credentials');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (($status = Auth::user()?->status) !== User::STATUS_ACTIVE) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey(), self::lockoutSeconds());
            $captcha->recordFailedAttempt($this, 'login');
            $this->auditFailure(strtolower((string) $status).' account');

            throw ValidationException::withMessages([
                'email' => User::signInRefusal($status),   // Manuscript – Naicel: Pending / Rejected say why
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $captcha->resetAttempts($this, 'login');
    }

    /** Recorded for the IT dashboard's security panel. The attempted email is never stored, only the IP (via the logger). */
    private function auditFailure(string $reason): void
    {
        \App\Services\SystemAuditLogger::record('Failed Login', $this, ['reason' => $reason]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), max(3, (int) \App\Support\SystemSettings::get('lockout_attempts', 5)))) {   // Manuscript – Naicel: Security settings
            return;
        }

        event(new Lockout($this));
        \App\Services\SystemAuditLogger::record('Login Lockout', $this, ['reason' => 'too many attempts']);

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    /** Manuscript – Naicel (2026-10-08): how long a lockout lasts (Security settings; default 1 minute). */
    private static function lockoutSeconds(): int
    {
        return 60 * max(1, (int) \App\Support\SystemSettings::get('lockout_minutes', 1));
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
