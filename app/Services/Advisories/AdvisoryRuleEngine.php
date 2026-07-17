<?php

namespace App\Services\Advisories;

use App\Models\AdvisoryRule;
use App\Models\ExternalWeatherData;
use App\Models\PlantingAdvisory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdvisoryRuleEngine
{
    public function generate(Collection $weatherRecords, array $context = []): array
    {
        $summary = [
            'advisories_created' => 0,
            'advisories_skipped_as_duplicates' => 0,
            'advisories_updated' => 0,
            'errors' => [],
        ];

        if ($weatherRecords->isEmpty()) {
            $summary['errors'][] = 'No stored weather forecast is available.';

            return $summary;
        }

        $metrics = $this->forecastMetrics($weatherRecords);
        $createdAdvisories = collect();

        AdvisoryRule::query()
            ->active()
            ->orderByDesc('priority')
            ->get()
            ->each(function (AdvisoryRule $rule) use ($weatherRecords, $context, $metrics, $createdAdvisories, &$summary): void {
                if (! $this->ruleMatches($rule, $metrics, $createdAdvisories, $context)) {
                    return;
                }

                $weather = $this->basisRecordForRule($rule, $weatherRecords);
                $validFrom = now();
                $validUntil = now()->addHours((int) data_get($rule->conditions, 'validity_hours', 24));
                $generationKey = $this->generationKey($rule, $weather, $validFrom, $validUntil);
                $requiresReview = (bool) data_get($rule->conditions, 'requires_review', false)
                    || in_array($rule->severity, [PlantingAdvisory::SEVERITY_CRITICAL], true)
                    || (($context['weather_freshness'] ?? 'fresh') === 'outdated' && in_array($rule->severity, ['high', 'critical'], true));

                $existing = PlantingAdvisory::query()->where('generation_key', $generationKey)->first();

                if ($existing) {
                    $summary['advisories_skipped_as_duplicates']++;

                    return;
                }

                DB::transaction(function () use ($rule, $weather, $metrics, $validFrom, $validUntil, $generationKey, $requiresReview, $createdAdvisories, &$summary): void {
                    $advisory = PlantingAdvisory::query()->create([
                        'title' => $rule->name,
                        'content' => $this->messageFor($rule, $metrics),
                        'type' => Str::headline($rule->advisory_type),
                        'advisory_type' => $rule->advisory_type,
                        'summary' => $this->summaryFor($rule, $metrics),
                        'message' => $this->messageFor($rule, $metrics),
                        'recommended_action' => $rule->recommendation,
                        'severity' => $rule->severity,
                        'priority' => $rule->priority,
                        'target_barangay' => null,
                        'target_scope' => 'municipality',
                        'source' => $requiresReview ? 'iClimate Decision Support' : 'Open-Meteo + iClimate Rules',
                        'source_url' => 'https://open-meteo.com/',
                        'weather_data_id' => $weather?->id,
                        'advisory_rule_id' => $rule->id,
                        'generation_key' => $generationKey,
                        'generated_automatically' => true,
                        'requires_review' => $requiresReview,
                        'status' => $requiresReview ? PlantingAdvisory::STATUS_PENDING_REVIEW : PlantingAdvisory::STATUS_PUBLISHED,
                        'valid_from' => $validFrom,
                        'valid_until' => $validUntil,
                        'published_at' => $requiresReview ? null : now(),
                        'metadata' => [
                            'weather_basis' => $this->weatherBasis($metrics, $weather),
                            'disclaimer' => $this->disclaimer(),
                        ],
                        'posted_by' => $this->systemUserId(),
                    ]);

                    $createdAdvisories->push($advisory);
                    $summary['advisories_created']++;
                });
            });

        return $summary;
    }

    public function forecastMetrics(Collection $records): array
    {
        $first = $records->first();
        $firstTwo = $records->take(2);
        $firstThree = $records->take(3);

        return [
            'forecast_start' => $first?->forecast_date?->toDateString(),
            'forecast_end' => $records->last()?->forecast_date?->toDateString(),
            'max_precipitation_probability' => (float) ($records->max('precipitation_probability') ?? 0),
            'max_temperature' => (float) ($records->max('temperature_max') ?? 0),
            'max_wind_speed' => (float) ($records->max('wind_speed') ?? 0),
            'max_daily_rainfall' => (float) ($records->max('rainfall_mm') ?? 0),
            'seven_day_rainfall' => (float) $records->sum('rainfall_mm'),
            'avg_temperature_max' => (float) ($records->avg('temperature_max') ?? 0),
            'probability_days_40' => $records->where('precipitation_probability', '>=', 40)->count(),
            'three_day_rainfall' => (float) $firstThree->sum('rainfall_mm'),
            'next_24h_rainfall' => (float) ($first?->rainfall_mm ?? 0),
            'next_24h_probability' => (float) ($first?->precipitation_probability ?? 0),
            'next_48h_rainfall' => (float) $firstTwo->sum('rainfall_mm'),
            'next_48h_probability' => (float) ($firstTwo->max('precipitation_probability') ?? 0),
            'avg_evapotranspiration' => (float) ($firstThree->avg('evapotranspiration_mm') ?? 0),
            'min_soil_moisture' => $records->whereNotNull('soil_moisture')->min('soil_moisture'),
            'two_consecutive_heavy_rain_days' => $this->hasConsecutiveHeavyRain($records),
        ];
    }

    private function ruleMatches(AdvisoryRule $rule, array $metrics, Collection $createdAdvisories, array $context): bool
    {
        if ($rule->requires_crop_data && empty($context['harvest_ready'])) {
            return false;
        }

        $conditions = $rule->conditions ?? [];

        return match ($rule->name) {
            'Heavy Rainfall Warning' => $metrics['max_precipitation_probability'] >= ($conditions['daily_precipitation_probability_min'] ?? 80)
                && $metrics['max_daily_rainfall'] >= ($conditions['daily_precipitation_sum_min'] ?? 30),
            'Severe Rainfall and Flooding Risk' => $metrics['max_daily_rainfall'] >= ($conditions['daily_precipitation_sum_min'] ?? 50)
                || $metrics['two_consecutive_heavy_rain_days'],
            'High Temperature Advisory' => $metrics['max_temperature'] >= ($conditions['temperature_max_min'] ?? 35),
            'Strong Wind Advisory' => $metrics['max_wind_speed'] >= ($conditions['wind_speed_max_min'] ?? 35),
            'Potentially Favorable Planting Conditions' => $this->between($metrics['seven_day_rainfall'], $conditions['seven_day_rainfall_between'] ?? [20, 70])
                && $this->between($metrics['avg_temperature_max'], $conditions['avg_temperature_max_between'] ?? [25, 33])
                && $metrics['probability_days_40'] >= ($conditions['probability_days_min'] ?? 2)
                && ! $createdAdvisories->contains(fn (PlantingAdvisory $advisory) => $advisory->severity === 'critical' || $advisory->title === 'Strong Wind Advisory'),
            'Consider Delaying Planting Activities' => $metrics['seven_day_rainfall'] >= ($conditions['seven_day_rainfall_min'] ?? 100)
                || $createdAdvisories->contains(fn (PlantingAdvisory $advisory) => $advisory->severity === 'critical'),
            'Supplemental Irrigation May Be Needed' => $metrics['three_day_rainfall'] < ($conditions['three_day_rainfall_max'] ?? 5)
                && $metrics['avg_evapotranspiration'] >= ($conditions['avg_evapotranspiration_min'] ?? 4)
                && ($metrics['min_soil_moisture'] === null || $metrics['min_soil_moisture'] < ($conditions['soil_moisture_max'] ?? 0.18)),
            'Consider Postponing Irrigation' => $metrics['next_24h_rainfall'] >= ($conditions['next_24h_rainfall_min'] ?? 15)
                || $metrics['next_24h_probability'] >= ($conditions['next_24h_probability_min'] ?? 80),
            'Consider Harvesting Before Expected Rainfall' => ! empty($context['harvest_ready'])
                && ($metrics['next_48h_probability'] >= ($conditions['next_48h_probability_min'] ?? 70) || $metrics['next_48h_rainfall'] >= ($conditions['next_48h_rainfall_min'] ?? 15)),
            'Delay Harvesting During Hazardous Weather' => ! empty($context['harvest_ready'])
                && $metrics['next_24h_rainfall'] >= 30
                && $metrics['max_wind_speed'] >= ($conditions['wind_speed_max_min'] ?? 35),
            default => false,
        };
    }

    private function basisRecordForRule(AdvisoryRule $rule, Collection $records): ?ExternalWeatherData
    {
        return match ($rule->name) {
            'High Temperature Advisory' => $records->sortByDesc('temperature_max')->first(),
            'Strong Wind Advisory' => $records->sortByDesc('wind_speed')->first(),
            default => $records->sortByDesc('rainfall_mm')->first(),
        };
    }

    private function generationKey(AdvisoryRule $rule, ?ExternalWeatherData $weather, $validFrom, $validUntil): string
    {
        return hash('sha256', implode('|', [
            $rule->id,
            'municipality',
            $weather?->forecast_date?->toDateString(),
            $rule->advisory_type,
            $validFrom->copy()->startOfDay()->toDateTimeString(),
            $validUntil->copy()->startOfDay()->toDateTimeString(),
        ]));
    }

    private function summaryFor(AdvisoryRule $rule, array $metrics): string
    {
        return match ($rule->name) {
            'Heavy Rainfall Warning' => 'Heavy rainfall is expected within the next 24 hours.',
            'Severe Rainfall and Flooding Risk' => 'Severe rainfall conditions may increase flooding risk in agricultural areas.',
            'High Temperature Advisory' => 'High temperatures may affect field work and soil moisture.',
            'Strong Wind Advisory' => 'Strong winds may affect seedlings, farm structures, and harvested produce.',
            'Potentially Favorable Planting Conditions' => 'Forecast conditions may support land preparation or planting activities.',
            'Consider Delaying Planting Activities' => 'Excessive rainfall may make planting activities risky.',
            'Supplemental Irrigation May Be Needed' => 'Low forecast rainfall and expected water loss may reduce field moisture.',
            'Consider Postponing Irrigation' => 'Significant rainfall is expected, so irrigation may be delayed when field conditions allow.',
            default => $rule->description ?: $rule->name,
        };
    }

    private function messageFor(AdvisoryRule $rule, array $metrics): string
    {
        $period = trim(($metrics['forecast_start'] ?? '').' to '.($metrics['forecast_end'] ?? ''));

        return $this->summaryFor($rule, $metrics).' Forecast period: '.$period.'. This advisory is generated from forecast data and iClimate decision-support rules.';
    }

    private function weatherBasis(array $metrics, ?ExternalWeatherData $weather): array
    {
        return [
            'expected_rainfall_mm' => $weather?->rainfall_mm,
            'rain_probability_percent' => $weather?->precipitation_probability,
            'maximum_temperature_c' => $weather?->temperature_max,
            'maximum_wind_speed_kmh' => $weather?->wind_speed,
            'forecast_period' => trim(($metrics['forecast_start'] ?? '').' to '.($metrics['forecast_end'] ?? '')),
            'seven_day_rainfall_mm' => $metrics['seven_day_rainfall'] ?? null,
        ];
    }

    private function hasConsecutiveHeavyRain(Collection $records): bool
    {
        $streak = 0;

        foreach ($records as $record) {
            $streak = ((float) $record->rainfall_mm) >= 30 ? $streak + 1 : 0;
            if ($streak >= 2) {
                return true;
            }
        }

        return false;
    }

    private function between(float $value, array $range): bool
    {
        return $value >= (float) $range[0] && $value <= (float) $range[1];
    }

    private function disclaimer(): string
    {
        return 'This advisory is generated from forecast data and iClimate decision-support rules. Actual farm conditions may differ. Consult the Municipal Agriculture Office and follow official PAGASA or local government warnings during severe weather.';
    }

    private function systemUserId(): int
    {
        $userId = User::query()
            ->whereIn('role', [User::ROLE_MAO, User::ROLE_IT_EXPERT])
            ->orderBy('id')
            ->value('id') ?? User::query()->orderBy('id')->value('id');

        if ($userId) {
            return (int) $userId;
        }

        return (int) User::query()->firstOrCreate(
            ['email' => 'system@iclimate.local'],
            [
                'name' => 'iClimate System',
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_IT_EXPERT,
                'status' => User::STATUS_ACTIVE,
            ],
        )->id;
    }
}
