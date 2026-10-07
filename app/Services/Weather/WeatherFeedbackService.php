<?php

namespace App\Services\Weather;

use App\Models\MonthlyWeatherPrediction;
use App\Models\User;
use App\Models\WeatherForecastFeedback;
use App\Services\Prediction\WeatherLabeler;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/**
 * Farmer "Was this forecast accurate?" (Weather Check) submission logic.
 *
 * Feedback is stored data only. This class never touches ClimateRecord,
 * the historical dataset, model files, or any retraining job.
 *
 * Rules:
 *  - Only the stored MONTHLY forecast (monthly_weather_predictions, status
 *    ready) can be rated; its forecast_date is the first day of the month.
 *  - A monthly forecast can be rated only once its whole month has ended
 *    (Asia/Manila), never before.
 *  - One answer per farmer per forecast; a resubmission is BLOCKED (no edits)
 *    with a friendly message.
 *  - "accurate" stores observed_condition = NULL (no fabricated data); the
 *    summary service treats accurate as "observed == forecast".
 *  - Barangay is taken from the farmer's profile at submission time and must
 *    be one of Lian's barangays.
 */
class WeatherFeedbackService
{
    public const TIMEZONE = 'Asia/Manila';

    public const RESULT_SAVED = 'saved';

    public const RESULT_DUPLICATE = 'duplicate';

    public const RESULT_NO_BARANGAY = 'no_barangay';

    public const RESULT_INVALID_BARANGAY = 'invalid_barangay';

    public const RESULT_NOT_OCCURRED = 'not_occurred';

    public const RESULT_NO_FORECAST = 'no_forecast';

    public const RESULT_NOT_FARMER = 'not_farmer';

    /**
     * Monthly rainfall is mapped onto the farmer-facing vocabulary using the
     * existing WeatherLabeler thresholds (unchanged). These are monthly-scale
     * approximations of the daily wording, not a new model output.
     */
    public static function conditionForRainfall(?float $rainfall): ?string
    {
        if ($rainfall === null) {
            return null;
        }

        return match (WeatherLabeler::fromRainfall($rainfall)) {
            'Heavy Rain' => 'Heavy Rain',
            'Rain' => 'Moderate Rain',
            'Dry' => 'No Rain',
            default => 'Cloudy',
        };
    }

    public static function periodHasEnded(CarbonImmutable $forecastMonthStart): bool
    {
        return CarbonImmutable::now(self::TIMEZONE)->greaterThan(
            $forecastMonthStart->setTimezone(self::TIMEZONE)->endOfMonth()
        );
    }

    /** @return array{0: ?string, 1: ?string} [canonical barangay, failure result] */
    public function barangayFor(User $user): array
    {
        $user->loadMissing('farmerProfile');
        $saved = $user->farmerProfile?->barangay ?: $user->barangay;

        if (blank($saved)) {
            return [null, self::RESULT_NO_BARANGAY];
        }

        $canonical = LianBarangays::canonicalName($saved);

        return $canonical ? [$canonical, null] : [null, self::RESULT_INVALID_BARANGAY];
    }

    public function storedForecast(CarbonImmutable $monthStart): ?MonthlyWeatherPrediction
    {
        return MonthlyWeatherPrediction::query()
            ->where('prediction_year', $monthStart->year)
            ->where('prediction_month', $monthStart->month)
            ->where('status', MonthlyWeatherPrediction::STATUS_READY)
            ->whereNotNull('rainfall')
            ->orderByDesc('generated_at')
            ->first();
    }

    /**
     * The forecast to ask a farmer about: the most recent ended month that has
     * a stored ready forecast and that this farmer has not answered yet.
     *
     * @return ?array{date: string, label: string, condition: string, rainfall_mm: float}
     */
    public function pendingPrompt(User $user): ?array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return null;
        }

        $answered = WeatherForecastFeedback::query()
            ->where('user_id', $user->id)
            ->where('forecast_type', WeatherForecastFeedback::TYPE_MONTHLY)
            ->pluck('forecast_date')
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->all();

        $now = CarbonImmutable::now(self::TIMEZONE);
        $rows = MonthlyWeatherPrediction::query()
            ->where('status', MonthlyWeatherPrediction::STATUS_READY)
            ->whereNotNull('rainfall')
            ->where(function ($query) use ($now): void {
                $query->where('prediction_year', '<', $now->year)
                    ->orWhere(fn ($q) => $q->where('prediction_year', $now->year)->where('prediction_month', '<', $now->month));
            })
            ->orderByDesc('prediction_year')
            ->orderByDesc('prediction_month')
            ->orderByDesc('generated_at')
            ->limit(24)
            ->get();

        foreach ($rows as $row) {
            $start = CarbonImmutable::create($row->prediction_year, $row->prediction_month, 1, 0, 0, 0, self::TIMEZONE);
            if (in_array($start->toDateString(), $answered, true)) {
                continue;
            }

            return [
                'date' => $start->toDateString(),
                'label' => $start->format('F Y'),
                'condition' => (string) self::conditionForRainfall((float) $row->rainfall),
                'rainfall_mm' => (float) $row->rainfall,
            ];
        }

        return null;
    }

    /** @return array{result: string, feedback: ?WeatherForecastFeedback} */
    public function submit(User $user, string $forecastDate, string $accuracy, ?string $observed): array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return ['result' => self::RESULT_NOT_FARMER, 'feedback' => null];
        }

        [$barangay, $failure] = $this->barangayFor($user);
        if ($failure) {
            return ['result' => $failure, 'feedback' => null];
        }

        $monthStart = CarbonImmutable::parse($forecastDate, self::TIMEZONE)->startOfMonth();
        if (! self::periodHasEnded($monthStart)) {
            return ['result' => self::RESULT_NOT_OCCURRED, 'feedback' => null];
        }

        $forecast = $this->storedForecast($monthStart);
        if (! $forecast) {
            return ['result' => self::RESULT_NO_FORECAST, 'feedback' => null];
        }

        $exists = WeatherForecastFeedback::query()
            ->where('user_id', $user->id)
            ->where('forecast_type', WeatherForecastFeedback::TYPE_MONTHLY)
            ->whereDate('forecast_date', $monthStart->toDateString())
            ->exists();
        if ($exists) {
            return ['result' => self::RESULT_DUPLICATE, 'feedback' => null];
        }

        try {
            $feedback = WeatherForecastFeedback::query()->create([
                'user_id' => $user->id,
                'barangay' => $barangay,
                'forecast_type' => WeatherForecastFeedback::TYPE_MONTHLY,
                'forecast_date' => $monthStart->toDateString(),
                'monthly_weather_prediction_id' => $forecast->id,
                'forecast_condition' => (string) self::conditionForRainfall((float) $forecast->rainfall),
                'forecast_rainfall_mm' => $forecast->rainfall,
                'forecast_temperature_c' => $forecast->temperature_average,
                'forecast_humidity_percent' => $forecast->humidity,
                'accuracy' => $accuracy,
                'observed_condition' => $accuracy === WeatherForecastFeedback::ACCURATE ? null : $observed,
                'submitted_at' => now(),
            ]);
        } catch (QueryException $exception) {
            // A concurrent double-tap can lose the race against the unique key.
            return ['result' => self::RESULT_DUPLICATE, 'feedback' => null];
        }

        return ['result' => self::RESULT_SAVED, 'feedback' => $feedback];
    }

    // ------------------------------------------------------------------ Farmer – Naicel (2026-10): the daily survey
    //
    // "Tama ba ang taya ng panahon kahapon?" on the Forecast page. Same table, same rules as the monthly check: stored
    // data only, one answer per farmer per day (forecast_type "daily" + the unique key), never before the day has
    // passed, "accurate" stores no observed condition. The forecast asked about is the stored daily forecast row for
    // yesterday (external_weather_data); nothing is fetched.

    /** The stored forecast for one past day, in the survey's vocabulary; null when none was saved for that day. */
    public function storedDailyForecast(CarbonImmutable $day): ?array
    {
        $row = \App\Models\ExternalWeatherData::query()
            ->whereDate('forecast_date', $day->toDateString())
            ->orderByDesc('fetched_at')->orderByDesc('id')
            ->first();
        if (! $row) {
            return null;
        }

        $rain = is_numeric($row->rainfall_mm) ? (float) $row->rainfall_mm : null;
        $chance = is_numeric($row->precipitation_probability) ? (float) $row->precipitation_probability : null;
        $wind = is_numeric($row->wind_speed) ? (float) $row->wind_speed : null;
        $code = is_numeric($row->weather_code) ? (int) $row->weather_code : null;
        $rainKey = \App\Services\Analytics\FarmerDashboardService::rainKey($rain, $chance);

        $condition = match (true) {
            $rainKey === 'heavy' => 'Heavy Rain',
            $rainKey === 'moderate' => 'Moderate Rain',
            $rainKey === 'light' => 'Light Rain',
            $wind !== null && $wind >= (float) config('farmer_dashboard.strong_wind_kmh', \App\Services\Analytics\FarmerDashboardService::STRONG_WIND_KMH) => 'Strong Wind',
            $code !== null && $code <= 1 => 'Sunny',
            $code !== null => 'Cloudy',
            default => 'No Rain',
        };

        return [
            'condition' => $condition,
            'rainfall_mm' => $rain,
            'temperature_c' => is_numeric($row->temperature_max) ? (float) $row->temperature_max : (is_numeric($row->temperature) ? (float) $row->temperature : null),
            'humidity_percent' => is_numeric($row->humidity) ? (float) $row->humidity : null,
        ];
    }

    /**
     * Yesterday's forecast, when this farmer has not answered about it yet and a forecast for it was saved.
     *
     * @return ?array{date: string, condition: string}
     */
    public function pendingDailyPrompt(User $user): ?array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return null;
        }
        $yesterday = CarbonImmutable::now(self::TIMEZONE)->subDay()->startOfDay();

        $answered = WeatherForecastFeedback::query()
            ->where('user_id', $user->id)
            ->where('forecast_type', WeatherForecastFeedback::TYPE_DAILY)
            ->whereDate('forecast_date', $yesterday->toDateString())
            ->exists();
        if ($answered) {
            return null;
        }
        $forecast = $this->storedDailyForecast($yesterday);

        return $forecast ? ['date' => $yesterday->toDateString(), 'condition' => $forecast['condition']] : null;
    }

    /** @return array{result: string, feedback: ?WeatherForecastFeedback} */
    public function submitDaily(User $user, string $forecastDate, string $accuracy, ?string $observed): array
    {
        if ($user->role !== User::ROLE_FARMER) {
            return ['result' => self::RESULT_NOT_FARMER, 'feedback' => null];
        }

        [$barangay, $failure] = $this->barangayFor($user);
        if ($failure) {
            return ['result' => $failure, 'feedback' => null];
        }

        $day = CarbonImmutable::parse($forecastDate, self::TIMEZONE)->startOfDay();
        $today = CarbonImmutable::now(self::TIMEZONE)->startOfDay();
        // Only a day that has passed, and only yesterday: the farmer still remembers it.
        if (! $day->equalTo($today->subDay())) {
            return ['result' => $day->greaterThanOrEqualTo($today) ? self::RESULT_NOT_OCCURRED : self::RESULT_NO_FORECAST, 'feedback' => null];
        }

        $forecast = $this->storedDailyForecast($day);
        if (! $forecast) {
            return ['result' => self::RESULT_NO_FORECAST, 'feedback' => null];
        }

        $exists = WeatherForecastFeedback::query()
            ->where('user_id', $user->id)
            ->where('forecast_type', WeatherForecastFeedback::TYPE_DAILY)
            ->whereDate('forecast_date', $day->toDateString())
            ->exists();
        if ($exists) {
            return ['result' => self::RESULT_DUPLICATE, 'feedback' => null];
        }

        try {
            $feedback = WeatherForecastFeedback::query()->create([
                'user_id' => $user->id,
                'barangay' => $barangay,
                'forecast_type' => WeatherForecastFeedback::TYPE_DAILY,
                'forecast_date' => $day->toDateString(),
                'monthly_weather_prediction_id' => null,
                'forecast_condition' => $forecast['condition'],
                'forecast_rainfall_mm' => $forecast['rainfall_mm'],
                'forecast_temperature_c' => $forecast['temperature_c'],
                'forecast_humidity_percent' => $forecast['humidity_percent'],
                'accuracy' => $accuracy,
                'observed_condition' => $accuracy === WeatherForecastFeedback::ACCURATE ? null : $observed,
                'submitted_at' => now(),
            ]);
        } catch (QueryException $exception) {
            return ['result' => self::RESULT_DUPLICATE, 'feedback' => null];
        }

        return ['result' => self::RESULT_SAVED, 'feedback' => $feedback];
    }

    // ------------------------------------------------------------------ end Farmer – Naicel

    public static function message(string $result): string
    {
        return match ($result) {
            self::RESULT_SAVED => 'Thank you! Your answer was saved.',
            self::RESULT_DUPLICATE => 'You already answered this - thank you.',
            self::RESULT_NO_BARANGAY => 'Please add your barangay to your profile first, then try again.',
            self::RESULT_INVALID_BARANGAY => 'Your saved barangay is not one of the Lian barangays. Please update your profile.',
            self::RESULT_NOT_OCCURRED => 'This forecast period is not over yet. Please answer after it has passed.',
            self::RESULT_NO_FORECAST => 'We could not find that forecast to check.',
            default => 'Only farmers can answer this question.',
        };
    }
}
