<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\CaptchaService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(Request $request, CaptchaService $captcha): View
    {
        return view('auth.register', [
            'captcha' => $captcha->challenge($request, 'register'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, CaptchaService $captcha): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'captcha_answer' => ['required', 'string', 'max:20'],
        ], [
            'captcha_answer.required' => 'Please answer the security check before creating an account.',
            'captcha_answer.max' => 'The security check answer is too long.',
        ]);

        if (! $captcha->validate($request, 'register', $validated['captcha_answer'] ?? null)) {
            throw ValidationException::withMessages([
                'captcha_answer' => 'The security check answer is incorrect. Please try again.',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'role' => User::ROLE_FARMER,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route($user->dashboardRoute(), absolute: false));
    }
}
