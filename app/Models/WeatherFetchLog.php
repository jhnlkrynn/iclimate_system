<?php

namespace App\Models;

use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class WeatherFetchLog extends Model
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    protected $fillable = ['provider', 'started_at', 'duration_ms', 'status', 'error_message'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'duration_ms' => 'integer'];
    }

    /** Never lets bookkeeping break a weather fetch. The message is shortened and stripped of the request URL. */
    public static function record(\DateTimeInterface $startedAt, int $durationMs, bool $ok, ?string $error = null, string $provider = 'Open-Meteo'): void
    {
        try {
            if (! SchemaCache::hasTable('weather_fetch_logs')) {
                return;
            }
            self::query()->create([
                'provider' => $provider,
                'started_at' => $startedAt,
                'duration_ms' => $durationMs,
                'status' => $ok ? self::SUCCESS : self::FAILED,
                'error_message' => $error === null ? null : mb_substr(preg_replace('#https?://\S+#', '[url]', $error) ?? $error, 0, 500),
            ]);
        } catch (Throwable) {
            // Logging is best effort.
        }
    }
}
