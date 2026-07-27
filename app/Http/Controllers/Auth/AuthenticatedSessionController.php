<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\Security\CaptchaService;
use App\Services\Weather\OpenMeteoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, CaptchaService $captcha, OpenMeteoService $weather): View
    {
        $forecastResult = $weather->fetchForecast($request->boolean('refresh_weather'));
        $weatherTimezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');
        $today = now($weatherTimezone)->toDateString();
        $forecastRecords = collect($forecastResult['records'] ?? [])->sortBy('forecast_date')->values();
        $latestForecast = $forecastRecords
            ->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
            ?? $forecastRecords->first();

        $requiresCaptcha = $captcha->requiresCaptcha($request, 'login');

        return view('auth.login', [
            'requiresCaptcha' => $requiresCaptcha,
            'captcha' => $requiresCaptcha ? $captcha->challenge($request, 'login') : null,
            'loginWeather' => [
                'forecast' => $latestForecast,
                'result' => $forecastResult,
                'timezone' => $weatherTimezone,
            ],
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()
            ->intended(route($request->user()->dashboardRoute(), absolute: false))
            ->with('success', 'Login successful. Welcome back to iClimate.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have logged out successfully.');
    }
}
