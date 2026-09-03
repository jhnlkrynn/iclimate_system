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
            RateLimiter::hit($this->throttleKey());
            $captcha->recordFailedAttempt($this, 'login');

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
            RateLimiter::hit($this->throttleKey());
            $captcha->recordFailedAttempt($this, 'login');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        if (Auth::user()?->status !== User::STATUS_ACTIVE) {
            Auth::logout();
            RateLimiter::hit($this->throttleKey());
            $captcha->recordFailedAttempt($this, 'login');

            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Please contact the administrator.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        $captcha->resetAttempts($this, 'login');
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

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
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
