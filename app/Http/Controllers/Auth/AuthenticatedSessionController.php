<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Security\CaptchaService;
use App\Services\SystemAuditLogger;
use App\Services\Weather\OpenMeteoService;
use App\Support\ClimateSnapshot;
use Illuminate\Http\JsonResponse;
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
        $requiresCaptcha = $captcha->requiresCaptcha($request, 'login');

        return view('auth.login', [
            'captcha' => $captcha->challenge($request, 'login'),
            'demoAccounts' => $this->demoAccounts(),
            'demoPassword' => (string) env('ICLIMATE_DEFAULT_ACCOUNT_PASSWORD', 'iClimate2026!'),
            'requiresCaptcha' => $requiresCaptcha,
            'loginWeather' => $this->weatherSnapshot($weather),
        ]);
    }

    /**
     * Return the current login-page weather snapshot as JSON, for live polling.
     *
     * Unlike the initial page render, this pulls a fresh reading from Open-Meteo
     * (subject to the service's own short-lived cache) so the widget reflects
     * live conditions instead of whatever was last stored.
     */
    public function weather(OpenMeteoService $weather): JsonResponse
    {
        return response()->json($this->weatherSnapshot($weather, live: true));
    }

    /**
     * Build the weather widget data shown on the login page.
     *
     * @return array<string, mixed>
     */
    private function weatherSnapshot(OpenMeteoService $weather, bool $live = false): array
    {
        $weatherTimezone = (string) config('services.open_meteo.timezone', 'Asia/Manila');

        try {
            $forecastRecords = $live
                ? collect($weather->fetchForecast()['records'] ?? [])
                : $weather->latestStoredForecast();
        } catch (Throwable) {
            $forecastRecords = collect();
        }

        $today = now($weatherTimezone)->toDateString();
        $forecastRecords = $forecastRecords->sortBy('forecast_date')->values();
        $forecast = $forecastRecords->first(fn ($record) => $record->forecast_date?->toDateString() >= $today)
            ?? $forecastRecords->first();

        $latestClimate = ClimateSnapshot::latest();

        $currentWeather = $forecast?->raw_response['current'] ?? [];
        $weatherCode = data_get($currentWeather, 'weather_code') ?? $forecast?->weather_code;
        $hasRealWeather = $forecast !== null || $latestClimate !== null;

        $temperature = data_get($currentWeather, 'temperature_2m') ?? $forecast?->temperature ?? $latestClimate?->temperature;
        $humidity = data_get($currentWeather, 'relative_humidity_2m') ?? $forecast?->humidity ?? $latestClimate?->humidity;
        $windSpeed = data_get($currentWeather, 'wind_speed_10m') ?? $forecast?->wind_speed ?? $latestClimate?->wind_speed;
        $rainfall = $forecast?->rainfall_mm ?? $latestClimate?->rainfall;
        $updatedAt = $forecast?->fetched_at?->timezone($weatherTimezone) ?? $latestClimate?->updated_at;

        $condition = match (true) {
            ! $hasRealWeather || $temperature === null => ['Weather data unavailable', '🌦️'],
            in_array((int) $weatherCode, [95, 96, 99], true) => ['Thunderstorm', '⛈️'],
            in_array((int) $weatherCode, [80, 81, 82, 61, 63, 65, 66, 67], true) => ['Rain', '🌧️'],
            in_array((int) $weatherCode, [51, 53, 55, 56, 57], true) => ['Drizzle', '🌧️'],
            in_array((int) $weatherCode, [45, 48], true) => ['Foggy', '🌫️'],
            in_array((int) $weatherCode, [1, 2, 3], true) => ['Cloudy', '⛅️'],
            $weatherCode !== null && (int) $weatherCode === 0 => ['Clear', '☀️'],
            (float) ($rainfall ?? 0) >= 120 => ['Rain', '🌧️'],
            (float) ($rainfall ?? 0) >= 70 => ['Cloudy', '⛅️'],
            (float) ($rainfall ?? 0) >= 30 => ['Partly Cloudy', '🌤️'],
            default => ['Sunny', '☀️'],
        };

        $hasUsableReading = $hasRealWeather && $temperature !== null;

        return [
            'has_real_weather' => $hasUsableReading,
            'temperature' => $hasUsableReading ? round((float) $temperature) : null,
            'humidity' => $humidity !== null ? round((float) $humidity) : null,
            'wind_speed' => $windSpeed !== null ? round((float) $windSpeed) : null,
            'rainfall' => $rainfall !== null ? round((float) $rainfall, 1) : null,
            'condition_label' => $condition[0],
            'condition_emoji' => $condition[1],
            'updated_at' => $updatedAt?->toIso8601String(),
            'updated_label' => $updatedAt
                ? 'Updated '.$updatedAt->shortTime()
                : ($hasUsableReading ? 'Stored climate record' : 'No live or stored weather available'),
        ];
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
