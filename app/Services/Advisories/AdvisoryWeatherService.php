<?php

namespace App\Services\Advisories;

use App\Models\PlantingAdvisory;
use App\Support\LianBarangays;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The weather shown under "Weather Behind This Advice".
 *
 * It answers one question: what does the configured forecast source say about
 * THIS advisory's place and time window? — not "what is the weather right now".
 *
 *  - Place: the advisory's target barangay (its own coordinates), otherwise the
 *    municipality of Lian.
 *  - Time: the advisory's valid_from → valid_until, read in Philippine time and
 *    aggregated from the source's hourly forecast (or its daily forecast for
 *    windows longer than a day and a half): total rain, highest chance of rain,
 *    highest temperature, strongest wind.
 *  - Source: Open-Meteo, the project's configured forecast service. PAGASA's
 *    advisory pages carry qualitative text only (no numeric rainfall,
 *    temperature or wind for Lian), so they are not used for the numbers.
 *
 * Nothing is estimated. A field the source does not return reads "Not provided
 * by source"; a window the source cannot cover reads "Weather data unavailable".
 * The data is a FORECAST (kept apart from observed weather and from ML
 * predictions), cached briefly, refreshed when stale, and a stale copy is only
 * ever used when clearly labelled with the time it was retrieved.
 */
class AdvisoryWeatherService
{
    public const TIMEZONE = 'Asia/Manila';

    private const HOURLY = ['temperature_2m', 'precipitation', 'precipitation_probability', 'wind_speed_10m', 'wind_gusts_10m'];

    private const DAILY = ['temperature_2m_max', 'precipitation_sum', 'precipitation_probability_max', 'wind_speed_10m_max', 'wind_gusts_10m_max'];

    /** Windows longer than this are summarised from the daily forecast. */
    private const HOURLY_MAX_HOURS = 36;

    private const FRESH_MINUTES = 20;

    public const ROWS = [
        'rainfall' => 'Expected rainfall',
        'probability' => 'Chance of rain',
        'temperature' => 'Maximum temperature',
        'wind' => 'Strongest wind',
    ];

    /**
     * @return array<string, mixed>
     */
    public function forAdvisory(PlantingAdvisory $advisory): array
    {
        $location = $this->location($advisory);
        [$start, $end, $windowNote] = $this->window($advisory);

        $result = [
            'kind' => 'Forecast',
            'location' => $location['label'],
            'window_note' => $windowNote,
            'source' => 'Open-Meteo weather forecast for '.$location['label'],
            'source_url' => 'https://open-meteo.com/',
            'issued' => 'Not provided by source',
            'updated' => null,
            'stale' => false,
            'status' => 'unavailable',
            'granularity' => null,
            'period' => $this->periodLabel($start, $end),
            'rows' => [],
            'messages' => [],
        ];

        if ($this->isPagasa($advisory)) {
            $result['messages'][] = 'PAGASA’s advisory text is qualitative and gives no numeric rainfall, temperature or wind for Lian, so these numbers come from the forecast source for the same place and period.';
        }

        $payload = $this->forecast($location['lat'], $location['lon']);

        if ($payload !== null) {
            $computed = $this->summarise($payload, $start, $end);

            if ($computed !== null) {
                $result['updated'] = $payload['fetched_at'];
                $result['stale'] = $payload['stale'] ?? false;
                $result['status'] = 'available';
                $result['granularity'] = $computed['granularity'];
                $result['period'] = $computed['period'];
                $result['rows'] = $computed['rows'];
                foreach ($computed['messages'] as $message) {
                    $result['messages'][] = $message;
                }
                if ($result['stale']) {
                    $result['messages'][] = 'The source could not be reached just now, so this is the last forecast retrieved (see "Last updated").';
                }

                return $result;
            }
        }

        // The source cannot cover this window (too far past/future, or unreachable):
        // fall back to the forecast that was saved when the advisory was generated.
        $snapshot = $this->snapshot($advisory);
        if ($snapshot !== null) {
            return $snapshot + $result + ['status' => 'snapshot'];
        }

        $result['messages'][] = match (true) {
            $payload === null => 'The forecast source could not be reached, and no forecast was saved with this advisory.',
            $end->lt(now(self::TIMEZONE)->subDays(2)->startOfDay()) => 'This advisory’s period has already passed and the source no longer provides forecast values for it.',
            $start->gt(now(self::TIMEZONE)->addDays(16)) => 'This advisory’s period is beyond the source’s 16-day forecast horizon.',
            default => 'The source did not provide forecast information for this advisory’s period.',
        };

        return $result;
    }

    /* ------------------------------------------------------------------ place & time */

    /** @return array{lat: float, lon: float, label: string} */
    private function location(PlantingAdvisory $advisory): array
    {
        $canonical = $advisory->target_barangay ? LianBarangays::canonicalName($advisory->target_barangay) : null;
        $coordinates = $canonical ? (LianBarangays::coordinates()[$canonical] ?? null) : null;

        if ($coordinates) {
            return ['lat' => (float) $coordinates[0], 'lon' => (float) $coordinates[1], 'label' => $canonical.', Lian, Batangas'];
        }

        return [
            'lat' => (float) config('services.open_meteo.latitude', \App\Support\LianLocation::LATITUDE),
            'lon' => (float) config('services.open_meteo.longitude', \App\Support\LianLocation::LONGITUDE),
            'label' => 'Lian, Batangas',
        ];
    }

    /**
     * The advisory's validity window in Philippine time, rounded to whole hours
     * (the forecast is hourly). A missing end means "the next 24 hours".
     *
     * @return array{0: Carbon, 1: Carbon, 2: ?string}
     */
    private function window(PlantingAdvisory $advisory): array
    {
        $note = null;
        $from = $advisory->valid_from ?? $advisory->published_at ?? $advisory->created_at ?? now();
        $until = $advisory->valid_until;

        $start = Carbon::parse($from)->timezone(self::TIMEZONE)->startOfHour()->addMinutes(Carbon::parse($from)->minute >= 30 ? 60 : 0);

        if ($until === null) {
            $end = $start->copy()->addDay();
            $note = 'This advisory has no end time, so the next 24 hours are shown.';
        } else {
            $end = Carbon::parse($until)->timezone(self::TIMEZONE)->startOfHour()->addMinutes(Carbon::parse($until)->minute >= 30 ? 60 : 0);
        }

        if ($end->lte($start)) {
            $end = $start->copy()->addHour();
        }

        return [$start, $end, $note];
    }

    private function isPagasa(PlantingAdvisory $advisory): bool
    {
        return stripos((string) $advisory->source, 'pagasa') !== false;
    }

    /* ------------------------------------------------------------------ source */

    /**
     * @return array<string, mixed>|null hourly + daily arrays, fetched_at, stale
     */
    private function forecast(float $lat, float $lon): ?array
    {
        $key = 'advisory-weather:'.number_format($lat, 3).':'.number_format($lon, 3);
        $cached = Cache::get($key);

        if ($cached && Carbon::parse($cached['fetched_at'])->gt(now()->subMinutes(self::FRESH_MINUTES))) {
            return $cached;
        }

        try {
            $response = Http::timeout((int) config('services.open_meteo.timeout', 10))
                ->retry(2, 300)
                ->acceptJson()
                ->get(rtrim((string) config('services.open_meteo.base_url', 'https://api.open-meteo.com/v1'), '/').'/forecast', [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'hourly' => implode(',', self::HOURLY),
                    'daily' => implode(',', self::DAILY),
                    'timezone' => self::TIMEZONE,
                    'past_days' => 2,
                    'forecast_days' => 16,
                ]);

            $json = $response->successful() ? $response->json() : null;

            if (is_array($json) && isset($json['hourly']['time'], $json['daily']['time'])) {
                $payload = ['fetched_at' => now()->toIso8601String(), 'hourly' => $json['hourly'], 'daily' => $json['daily'], 'stale' => false];
                Cache::put($key, $payload, now()->addMinutes(self::FRESH_MINUTES));
                Cache::put($key.':last', $payload, now()->addDays(3));

                return $payload;
            }
        } catch (Throwable) {
            // fall through to the last good copy
        }

        $last = Cache::get($key.':last');

        return $last ? ['stale' => true] + $last : null;
    }

    /* ------------------------------------------------------------------ maths */

    /**
     * @param  array<string, mixed>  $payload
     * @return array{granularity: string, period: string, rows: array<int, array<string, mixed>>, messages: array<int, string>}|null
     */
    private function summarise(array $payload, Carbon $start, Carbon $end): ?array
    {
        $hours = $end->diffInHours($start, true);

        return $hours <= self::HOURLY_MAX_HOURS
            ? $this->summariseHourly($payload['hourly'], $start, $end)
            : $this->summariseDaily($payload['daily'], $start, $end);
    }

    /** @param  array<string, array<int, mixed>>  $hourly */
    private function summariseHourly(array $hourly, Carbon $start, Carbon $end): ?array
    {
        $indexes = [];
        foreach ($hourly['time'] as $i => $time) {
            $at = Carbon::parse($time, self::TIMEZONE);
            if ($at->gte($start) && $at->lt($end)) {
                $indexes[] = $i;
            }
        }

        if ($indexes === []) {
            return null;
        }

        $messages = [];
        $coveredStart = Carbon::parse($hourly['time'][$indexes[0]], self::TIMEZONE);
        $coveredEnd = Carbon::parse($hourly['time'][end($indexes)], self::TIMEZONE)->addHour();
        if ($coveredStart->gt($start) || $coveredEnd->lt($end)) {
            $messages[] = 'The source’s forecast covers only part of this advisory’s period; the values below are for the covered part.';
        }

        $pick = fn (string $key): array => array_values(array_filter(
            array_map(fn ($i) => $hourly[$key][$i] ?? null, $indexes),
            fn ($v) => is_numeric($v),
        ));

        $rain = $pick('precipitation');
        $prob = $pick('precipitation_probability');
        $temp = $pick('temperature_2m');
        $wind = $pick('wind_speed_10m');
        $gust = $pick('wind_gusts_10m');

        return [
            'granularity' => 'hourly',
            'period' => $this->periodLabel($coveredStart, $coveredEnd),
            'rows' => $this->rows(
                $rain === [] ? null : array_sum($rain),
                $prob === [] ? null : max($prob),
                $temp === [] ? null : max($temp),
                $wind === [] ? null : max($wind),
                $gust === [] ? null : max($gust),
            ),
            'messages' => $messages,
        ];
    }

    /** @param  array<string, array<int, mixed>>  $daily */
    private function summariseDaily(array $daily, Carbon $start, Carbon $end): ?array
    {
        $indexes = [];
        $lastMoment = $end->copy()->subSecond();
        foreach ($daily['time'] as $i => $date) {
            $day = Carbon::parse($date, self::TIMEZONE)->startOfDay();
            if ($day->lte($lastMoment) && $day->copy()->endOfDay()->gte($start)) {
                $indexes[] = $i;
            }
        }

        if ($indexes === []) {
            return null;
        }

        $firstDay = Carbon::parse($daily['time'][$indexes[0]], self::TIMEZONE)->startOfDay();
        $lastDay = Carbon::parse($daily['time'][end($indexes)], self::TIMEZONE)->endOfDay();

        $messages = ['This period is longer than a day and a half, so whole days from the daily forecast are used.'];
        if ($lastDay->lt($lastMoment->copy()->startOfDay())) {
            $messages[] = 'The source’s forecast ends before this advisory does; the values below are for the days it covers.';
        }

        $pick = fn (string $key): array => array_values(array_filter(
            array_map(fn ($i) => $daily[$key][$i] ?? null, $indexes),
            fn ($v) => is_numeric($v),
        ));

        $rain = $pick('precipitation_sum');
        $prob = $pick('precipitation_probability_max');
        $temp = $pick('temperature_2m_max');
        $wind = $pick('wind_speed_10m_max');
        $gust = $pick('wind_gusts_10m_max');

        return [
            'granularity' => 'daily',
            'period' => $firstDay->format('M j').($firstDay->isSameDay($lastDay) ? '' : ' – '.$lastDay->format('M j, Y')).' (whole days, Philippine time)',
            'rows' => $this->rows(
                $rain === [] ? null : array_sum($rain),
                $prob === [] ? null : max($prob),
                $temp === [] ? null : max($temp),
                $wind === [] ? null : max($wind),
                $gust === [] ? null : max($gust),
            ),
            'messages' => $messages,
        ];
    }

    /**
     * @return array<int, array{key: string, label: string, value: ?string, detail: ?string}>
     */
    private function rows(?float $rain, ?float $probability, ?float $temperature, ?float $wind, ?float $gust): array
    {
        $trim = fn (float $v): string => rtrim(rtrim(number_format($v, 1), '0'), '.');

        return [
            ['key' => 'rainfall', 'label' => self::ROWS['rainfall'], 'value' => $rain === null ? null : $trim($rain).' mm', 'detail' => 'total over the period'],
            ['key' => 'probability', 'label' => self::ROWS['probability'], 'value' => $probability === null ? null : round($probability).'%', 'detail' => 'highest chance of rain in the period'],
            ['key' => 'temperature', 'label' => self::ROWS['temperature'], 'value' => $temperature === null ? null : $trim($temperature).'°C', 'detail' => null],
            ['key' => 'wind', 'label' => self::ROWS['wind'], 'value' => $wind === null ? null : $trim($wind).' km/h', 'detail' => $gust === null ? 'sustained wind' : 'sustained wind · gusts up to '.$trim($gust).' km/h'],
        ];
    }

    /* ------------------------------------------------------------------ saved forecast */

    /**
     * The forecast stored with the advisory when it was generated (rule-based
     * advisories save one). Used only when the live source cannot cover the
     * advisory's period, and labelled as what it is.
     *
     * @return array<string, mixed>|null
     */
    private function snapshot(PlantingAdvisory $advisory): ?array
    {
        $basis = $advisory->metadata['weather_basis'] ?? null;
        if (! is_array($basis) || ! is_numeric($basis['expected_rainfall_mm'] ?? null)) {
            return null;
        }

        $trim = fn ($v): ?string => is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 1), '0'), '.') : null;
        $rain = $trim($basis['expected_rainfall_mm'] ?? null);
        $prob = is_numeric($basis['rain_probability_percent'] ?? null) ? round((float) $basis['rain_probability_percent']).'%' : null;
        $temp = $trim($basis['maximum_temperature_c'] ?? null);
        $wind = $trim($basis['maximum_wind_speed_kmh'] ?? null);

        return [
            'status' => 'snapshot',
            'kind' => 'Forecast (saved when this advisory was generated)',
            'granularity' => 'saved',
            'period' => trim((string) ($basis['forecast_period'] ?? '')) ?: 'Not provided by source',
            'rows' => [
                ['key' => 'rainfall', 'label' => self::ROWS['rainfall'], 'value' => $rain === null ? null : $rain.' mm', 'detail' => 'as saved'],
                ['key' => 'probability', 'label' => self::ROWS['probability'], 'value' => $prob, 'detail' => 'as saved'],
                ['key' => 'temperature', 'label' => self::ROWS['temperature'], 'value' => $temp === null ? null : $temp.'°C', 'detail' => 'as saved'],
                ['key' => 'wind', 'label' => self::ROWS['wind'], 'value' => $wind === null ? null : $wind.' km/h', 'detail' => 'as saved'],
            ],
            'updated' => $advisory->created_at?->toIso8601String(),
            'messages' => ['The live source can no longer provide a forecast for this advisory’s period, so the forecast saved with the advisory when it was generated is shown.'],
        ];
    }

    private function periodLabel(Carbon $start, Carbon $end): string
    {
        $end = $end->copy();
        $sameDay = $start->isSameDay($end) || ($end->hour === 0 && $end->minute === 0 && $start->copy()->addDay()->isSameDay($end));

        return $start->format('M j, g A').' – '.($sameDay ? $end->format('g A') : $end->format('M j, g A')).' (Philippine time)';
    }
}
