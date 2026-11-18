<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Accounts\FarmerAccountReviewService;
use App\Services\Security\CaptchaService;
use App\Support\SystemSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class FarmerResubmissionController extends Controller
{
    public function create(Request $request, CaptchaService $captcha)
    {
        return view('auth.resubmit-registration', ['requiresCaptcha' => $captcha->requiresCaptcha($request, 'login')]);
    }

    public function store(LoginRequest $request, FarmerAccountReviewService $reviews, CaptchaService $captcha)
    {
        $request->ensureIsNotRateLimited();
        if (! FarmerAccountReviewService::available()) {
            // Checked before the password and captcha, so this never counts as a failed sign-in attempt.
            return back()->withInput($request->only('email'))->withErrors(['resubmission_note' => FarmerAccountReviewService::NOT_READY_MESSAGE]);
        }
        $request->validate(['resubmission_note' => ['required', 'string', 'min:10', 'max:2000']]);
        try {
            if ($captcha->requiresCaptcha($request, 'login') && ! $captcha->validate($request, 'login', $request->input('captcha_answer'))) {
                throw ValidationException::withMessages(['captcha_answer' => 'The security check answer is incorrect. Please try again.']);
            }
            $reviews->resubmit($request);
        } catch (ValidationException $error) {
            $captcha->recordFailedAttempt($request, 'login');
            RateLimiter::hit($request->throttleKey(), 60 * max(1, (int) SystemSettings::get('lockout_minutes', 1)));
            throw $error;
        }
        $captcha->resetAttempts($request, 'login');
        RateLimiter::clear($request->throttleKey());

        return redirect()->route('login')->with('status', 'Your corrections were submitted. MAO Staff will review your application.');
    }
}
