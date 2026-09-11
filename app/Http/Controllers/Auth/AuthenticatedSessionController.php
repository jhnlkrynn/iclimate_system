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
use Throwable;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request, CaptchaService $captcha, OpenMeteoService $weather): View
    {
        $weatherTimezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');
        $forecastRecords = collect();

        try {
            $forecastRecords = $weather->latestStoredForecast();
        } catch (Throwable) {
            $forecastRecords = collect();
        }

        $forecastResult = [
            'ok' => $forecastRecords->isNotEmpty(),
            'source' => 'Open-Meteo',
            'source_name' => 'Open-Meteo Forecast API',
            'source_url' => 'https://open-meteo.com/',
            'source_note' => $forecastRecords->isNotEmpty()
                ? 'Stored forecast from the latest successful Open-Meteo update.'
                : 'Weather preview is temporarily unavailable.',
            'freshness' => $forecastRecords->first()?->freshnessLabel() ?? 'unavailable',
            'records' => $forecastRecords,
            'records_saved' => 0,
            'fetched_at' => $forecastRecords->first()?->fetched_at,
            'message' => $forecastRecords->isNotEmpty()
                ? 'Showing the latest stored Lian, Batangas weather update.'
                : 'Weather preview is temporarily unavailable. You can still sign in.',
        ];

        $today = now($weatherTimezone)->toDateString();
        $forecastRecords = $forecastRecords->sortBy('forecast_date')->values();
        $latestForecast = $forecastRecords
            ->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
            ?? $forecastRecords->first();

        $requiresCaptcha = $captcha->requiresCaptcha($request, 'login');

        return view('auth.login', [
            'captcha' => $captcha->challenge($request, 'login'),
            'demoAccounts' => $this->demoAccounts(),
            'demoPassword' => (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', 'iClimate2026!'),
            'requiresCaptcha' => $requiresCaptcha,
            'loginWeather' => [
                'forecast' => $latestForecast,
                'result' => $forecastResult,
                'timezone' => $weatherTimezone,
            ],
        ]);
    }

    /**
     * Demo credentials shown on the login page.
     *
     * @return array<int, array{role: string, email: string}>
     */
    private function demoAccounts(): array
    {
        return [
            ['role' => User::ROLE_FARMER, 'email' => 'farmer@iclimate.com'],
            ['role' => User::ROLE_MAO, 'email' => 'mao@iclimate.com'],
            ['role' => User::ROLE_IT_EXPERT, 'email' => 'admin@iclimate.com'],
        ];
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
}
