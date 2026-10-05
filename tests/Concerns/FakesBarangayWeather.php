<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Answers Open-Meteo requests the way the provider does: a batched request (comma-separated coordinates)
 * gets a list with one result per coordinate, a single-coordinate request gets one object. $payloadFor
 * receives each requested [latitude, longitude] and returns that point's payload, or null to fail it.
 */
trait FakesBarangayWeather
{
    /** @return array<int, array{0: float, 1: float}> */
    protected function requestedPoints(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);
        $latitudes = explode(',', (string) ($query['latitude'] ?? ''));
        $longitudes = explode(',', (string) ($query['longitude'] ?? ''));

        return array_map(fn ($lat, $lon): array => [(float) $lat, (float) $lon], $latitudes, $longitudes);
    }

    protected function openMeteoAnswer(Request $request, callable $payloadFor)
    {
        $points = $this->requestedPoints($request);
        $payloads = array_map(fn (array $point) => $payloadFor($point[0], $point[1]), $points);

        if (in_array(null, $payloads, true)) {
            return Http::response(['error' => true, 'reason' => 'Provider failure (test)'], 503);
        }

        $payloads = array_map(fn (array $payload, array $point): array => $payload + ['latitude' => $point[0], 'longitude' => $point[1]], $payloads, $points);

        return Http::response(count($points) > 1 ? $payloads : $payloads[0], 200);
    }
}
