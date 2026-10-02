<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\Security\CaptchaService;
use App\Services\SystemAuditLogger;
use App\Services\Weather\FarmerDashboardWeatherService;
use App\Support\ClimateSnapshot;
use Illuminate\Support\Carbon;
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
    public function create(Request $request, CaptchaService $captcha, FarmerDashboardWeatherService $weather): View
    {
        $requiresCaptcha = $captcha->requiresCaptcha($request, 'login');

        return view('auth.login', [
            'captcha' => $captcha->challenge($request, 'login'),
            'demoAccounts' => $this->demoAccounts(),
            'demoPassword' => (string) config('iclimate.default_account_password'),
            'requiresCaptcha' => $requiresCaptcha,
            // The login page itself never calls a weather provider: it shows whatever is already stored, and
            // the page's own script asks login/weather (below) for the live reading after it has loaded.
            'loginWeather' => $this->weatherSnapshot($weather, storedOnly: true),
        ]);
    }

    /**
     * Return the current login-page weather snapshot as JSON, for live polling.
     *
     * Reads the same shared (cached) Lian reading as the dashboards.
     */
    public function weather(FarmerDashboardWeatherService $weather): JsonResponse
    {
        return response()->json($this->weatherSnapshot($weather));
    }

    /**
     * Build the weather widget data shown on the login page.
     *
     * @return array<string, mixed>
     */
    private function weatherSnapshot(FarmerDashboardWeatherService $weather, bool $storedOnly = false): array
    {
        $weatherTimezone = (string) config('services.weather.timezone', 'Asia/Manila');

        // Same shared Lian reading the Farmer/MAO dashboards and chatbot use,
        // so the login widget never shows a different temperature.
        try {
            $shared = $storedOnly ? ($weather->stored() ?? []) : $weather->current();
        } catch (Throwable) {
            $shared = [];
        }

        $hasShared = ($shared['success'] ?? false) === true;
        $latestClimate = ClimateSnapshot::latest();

        $weatherCode = $hasShared ? data_get($shared, 'current.weather_code') : null;
        $hasRealWeather = $hasShared || $latestClimate !== null;

        $temperature = ($hasShared ? data_get($shared, 'current.temperature') : null) ?? $latestClimate?->temperature;
        $humidity = ($hasShared ? data_get($shared, 'current.humidity') : null) ?? $latestClimate?->humidity;
        $windSpeed = ($hasShared ? data_get($shared, 'current.wind_speed') : null) ?? $latestClimate?->wind_speed;
        $rainfall = ($hasShared ? data_get($shared, 'today.rainfall') : null) ?? $latestClimate?->rainfall;
        $fetchedAt = $hasShared ? ($shared['fetched_at'] ?? null) : null;
        $updatedAt = $fetchedAt
            ? Carbon::parse($fetchedAt)->timezone($weatherTimezone)
            : $latestClimate?->updated_at;

        $sharedCondition = $hasShared ? strtolower((string) data_get($shared, 'current.condition', '')) : '';

        $condition = match (true) {
            ! $hasRealWeather || $temperature === null => ['Weather data unavailable', '🌦️'],
            // Shared reading: reuse its own condition label so the wording
            // matches the dashboards (WeatherAPI codes are not WMO codes).
            $sharedCondition !== '' => [
                data_get($shared, 'current.condition'),
                match (true) {
                    str_contains($sharedCondition, 'thunder') => '⛈️',
                    str_contains($sharedCondition, 'rain') || str_contains($sharedCondition, 'drizzle') || str_contains($sharedCondition, 'shower') => '🌧️',
                    str_contains($sharedCondition, 'fog') => '🌫️',
                    str_contains($sharedCondition, 'partly') => '🌤️',
                    str_contains($sharedCondition, 'cloud') || str_contains($sharedCondition, 'overcast') => '⛅️',
                    str_contains($sharedCondition, 'night') => '🌙',
                    default => '☀️',
                },
            ],
            in_array((int) $weatherCode, [95, 96, 99], true) => ['Thunderstorm', '⛈️'],
            in_array((int) $weatherCode, [80, 81, 82, 61, 63, 65, 66, 67], true) => ['Rain', '🌧️'],
            in_array((int) $weatherCode, [51, 53, 55, 56, 57], true) => ['Light Rain', '🌧️'],
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
            'temperature' => $hasUsableReading ? round((float) $temperature, 1) : null,
            'humidity' => $humidity !== null ? round((float) $humidity) : null,
            'wind_speed' => $windSpeed !== null ? round((float) $windSpeed, 1) : null,
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
