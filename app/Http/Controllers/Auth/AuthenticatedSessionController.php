<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\SystemAuditLogger;
use App\Services\Weather\FarmerDashboardWeatherService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

class AuthenticatedSessionController extends Controller
{
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
    private function weatherSnapshot(FarmerDashboardWeatherService $weather): array
    {
        $weatherTimezone = (string) config('services.weather.timezone', 'Asia/Manila');

        // Same shared Lian reading the Farmer/MAO dashboards and chatbot use,
        // so the login widget never shows a different temperature.
        try {
            $shared = $weather->current();
        } catch (Throwable) {
            $shared = [];
        }

        $hasShared = ($shared['success'] ?? false) === true;

        $weatherCode = $hasShared ? data_get($shared, 'current.weather_code') : null;
        $hasRealWeather = $hasShared;

        $temperature = $hasShared ? data_get($shared, 'current.temperature') : null;
        $humidity = $hasShared ? data_get($shared, 'current.humidity') : null;
        $windSpeed = $hasShared ? data_get($shared, 'current.wind_speed') : null;
        $rainfall = $hasShared ? data_get($shared, 'today.rainfall_to_date_mm') : null;
        $fetchedAt = $hasShared ? ($shared['fetched_at'] ?? null) : null;
        $updatedAt = $fetchedAt
            ? Carbon::parse($fetchedAt)->timezone($weatherTimezone)
            : null;

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
            default => ['Weather data unavailable', '🌦️'],
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
                ? (($shared['stale'] ?? false) ? 'Last updated '.$updatedAt->shortTime() : 'Updated '.$updatedAt->shortTime())
                : 'No live or stored weather available',
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

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->saveQuietly();   // Manuscript – Naicel: Last Login on the user list

        // Every sign-in lands on the role's own dashboard (User::dashboardRoute()). A page a guest was sent to login
        // from (the heat map, an advisory, a bookmark) is forgotten, not followed: it could be one another person
        // opened on this browser, and it made a landing-page login end on the heat map.
        $request->session()->forget('url.intended');

        // No "login successful" message: landing on the dashboard already says so.
        return redirect()->route($user->dashboardRoute());
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

        // Back to the landing page with the sign-in card open, ready for the next person.
        return redirect(url('/').'#login')->with('success', 'You have logged out successfully.');
    }
}
