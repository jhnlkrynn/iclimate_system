<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Security\CaptchaService;
use App\Services\SystemAuditLogger;
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
        $forecastResult = $weather->fetchForecast(! $request->boolean('cached_weather'));
        $weatherTimezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');
        $today = now($weatherTimezone)->toDateString();
        $forecastRecords = collect($forecastResult['records'] ?? [])->sortBy('forecast_date')->values();
        $latestForecast = $forecastRecords
            ->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
            ?? $forecastRecords->first();

        return view('auth.login', [
            'captcha' => $captcha->challenge($request, 'login'),
            'demoAccounts' => $this->demoAccounts(),
            'demoPassword' => (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', 'iClimate2026!'),
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
        SystemAuditLogger::record('User Login', $request);

        return redirect()
            ->intended(route($request->user()->dashboardRoute(), absolute: false))
            ->with('success', 'Login successful. Welcome back to iClimate.');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        SystemAuditLogger::record('User Logout', $request, user: $user);

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'You have logged out successfully.');
    }

    private function demoAccounts()
    {
        return User::query()
            ->whereIn('email', ['farmer@iclimate.com', 'mao@iclimate.com', 'admin@iclimate.com'])
            ->where('status', User::STATUS_ACTIVE)
            ->orderByRaw('case role when ? then 1 when ? then 2 when ? then 3 else 4 end', [
                User::ROLE_FARMER,
                User::ROLE_MAO,
                User::ROLE_IT_EXPERT,
            ])
            ->get(['name', 'email', 'role', 'status']);
    }
}
