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
        if (! empty($context['advisory_horizons']) && is_array($context['advisory_horizons'])) {
            return $this->generateForHorizons($weatherRecords, $context);
        }

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
                $validUntil = now()->addHours((int) ($context['validity_hours_override'] ?? data_get($rule->conditions, 'validity_hours', 24)));
                $generationKey = $this->generationKey($rule, $weather, $validFrom, $validUntil, $context);
                $requiresReview = (bool) data_get($rule->conditions, 'requires_review', false)
                    || in_array($rule->severity, [PlantingAdvisory::SEVERITY_CRITICAL], true)
                    || (($context['weather_freshness'] ?? 'fresh') === 'outdated' && in_array($rule->severity, ['high', 'critical'], true));

                $existing = PlantingAdvisory::query()->where('generation_key', $generationKey)->first();

                if ($existing) {
                    $summary['advisories_skipped_as_duplicates']++;

                    return;
                }

                DB::transaction(function () use ($rule, $weather, $metrics, $validFrom, $validUntil, $generationKey, $requiresReview, $createdAdvisories, $context, &$summary): void {
                    $advisory = PlantingAdvisory::query()->create([
                        'title' => $this->titleFor($rule, $context),
                        'content' => $this->messageFor($rule, $metrics, $context),
                        'type' => Str::headline($rule->advisory_type),
                        'advisory_type' => $rule->advisory_type,
                        'summary' => $this->summaryFor($rule, $metrics, $context),
                        'message' => $this->messageFor($rule, $metrics, $context),
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
                            'advisory_horizon' => $context['advisory_horizon'] ?? 'forecast',
                            'advisory_horizon_label' => $context['advisory_horizon_label'] ?? 'Forecast',
                            'advisory_horizon_description' => $context['advisory_horizon_description'] ?? null,
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

    private function generateForHorizons(Collection $weatherRecords, array $context): array
    {
        $summary = [
            'advisories_created' => 0,
            'advisories_skipped_as_duplicates' => 0,
            'advisories_updated' => 0,
            'errors' => [],
        ];

        foreach ($context['advisory_horizons'] as $horizon) {
            $definition = $this->horizonDefinitions()[(string) $horizon] ?? null;

            if (! $definition) {
                continue;
            }

            $records = $weatherRecords
                ->sortBy('forecast_date')
                ->take($definition['days'])
                ->values();

            if ($records->isEmpty()) {
                $summary['errors'][] = "No weather forecast records are available for the {$definition['label']} advisory horizon.";

                continue;
            }

            $horizonContext = array_merge($context, [
                'advisory_horizons' => null,
                'advisory_horizon' => $horizon,
                'advisory_horizon_label' => $definition['label'],
                'advisory_horizon_description' => $definition['description'],
                'validity_hours_override' => $definition['validity_hours'],
            ]);

            $result = $this->generate($records, $horizonContext);

            if (($result['advisories_created'] ?? 0) === 0 && ($result['advisories_skipped_as_duplicates'] ?? 0) === 0) {
                $baselineResult = $this->createBaselineAdvisory($records, $horizonContext);
                $result['advisories_created'] += $baselineResult['created'];
                $result['advisories_skipped_as_duplicates'] += $baselineResult['skipped'];
            }

            $summary['advisories_created'] += $result['advisories_created'];
            $summary['advisories_skipped_as_duplicates'] += $result['advisories_skipped_as_duplicates'];
            $summary['advisories_updated'] += $result['advisories_updated'];
            $summary['errors'] = array_merge($summary['errors'], $result['errors']);
        }

        return $summary;
    }

    private function createBaselineAdvisory(Collection $records, array $context): array
    {
        $metrics = $this->forecastMetrics($records);
        $weather = $records->sortByDesc('precipitation_probability')->first() ?: $records->first();
        $horizon = (string) ($context['advisory_horizon'] ?? 'forecast');
        $label = (string) ($context['advisory_horizon_label'] ?? 'Forecast');
        $validFrom = now();
        $validUntil = now()->addHours((int) ($context['validity_hours_override'] ?? 24));
        $generationKey = hash('sha256', implode('|', [
            'baseline-weather-watch',
            $horizon,
            $this->generationPeriodStamp($validFrom, $horizon),
            $this->generationPeriodStamp($validUntil, $horizon),
        ]));

        if (PlantingAdvisory::query()->where('generation_key', $generationKey)->exists()) {
            return ['created' => 0, 'skipped' => 1];
        }

        PlantingAdvisory::query()->create([
            'title' => "{$label} Lian Weather Watch",
            'content' => $this->baselineMessage($metrics, $context),
            'type' => 'Climate',
            'advisory_type' => PlantingAdvisory::TYPE_CLIMATE,
            'summary' => "{$label} outlook: No severe iClimate rule was triggered, but farmers should keep monitoring current Lian, Batangas weather.",
            'message' => $this->baselineMessage($metrics, $context),
            'recommended_action' => 'Continue normal farm planning, check actual field conditions, monitor updated advisories, and follow MAO, LGU, or PAGASA online guidance if conditions change.',
            'severity' => PlantingAdvisory::SEVERITY_INFORMATION,
            'priority' => 20,
            'target_barangay' => null,
            'target_scope' => 'municipality',
            'source' => 'Open-Meteo + iClimate Rules',
            'source_url' => 'https://open-meteo.com/',
            'weather_data_id' => $weather?->id,
            'advisory_rule_id' => null,
            'generation_key' => $generationKey,
            'generated_automatically' => true,
            'requires_review' => false,
            'status' => PlantingAdvisory::STATUS_PUBLISHED,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'published_at' => now(),
            'metadata' => [
                'weather_basis' => $this->weatherBasis($metrics, $weather),
                'advisory_horizon' => $horizon,
                'advisory_horizon_label' => $label,
                'advisory_horizon_description' => $context['advisory_horizon_description'] ?? null,
                'baseline_advisory' => true,
                'disclaimer' => $this->disclaimer(),
            ],
            'posted_by' => $this->systemUserId(),
        ]);

        return ['created' => 1, 'skipped' => 0];
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

    private function generationKey(AdvisoryRule $rule, ?ExternalWeatherData $weather, $validFrom, $validUntil, array $context = []): string
    {
        $horizon = (string) ($context['advisory_horizon'] ?? 'forecast');

        return hash('sha256', implode('|', [
            $rule->id,
            'municipality',
            $horizon,
            $weather?->forecast_date?->toDateString(),
            $rule->advisory_type,
            $this->generationPeriodStamp($validFrom, $horizon),
            $this->generationPeriodStamp($validUntil, $horizon),
        ]));
    }

    private function titleFor(AdvisoryRule $rule, array $context): string
    {
        $label = $context['advisory_horizon_label'] ?? null;

        return $label ? "{$label} {$rule->name}" : $rule->name;
    }

    private function summaryFor(AdvisoryRule $rule, array $metrics, array $context = []): string
    {
        $horizon = (string) ($context['advisory_horizon'] ?? 'forecast');

        $summary = match ($rule->name) {
            'Heavy Rainfall Warning' => $horizon === 'daily' || $horizon === 'hourly'
                ? 'Heavy rainfall is expected in the near-term forecast window.'
                : 'Heavy rainfall appears within the checked forecast outlook.',
            'Severe Rainfall and Flooding Risk' => $horizon === 'daily' || $horizon === 'hourly'
                ? 'Severe rainfall conditions may increase flooding risk in agricultural areas soon.'
                : 'Severe rainfall signals appear in the checked forecast outlook and may increase flood risk.',
            'High Temperature Advisory' => 'High temperatures may affect field work and soil moisture.',
            'Strong Wind Advisory' => 'Strong winds may affect seedlings, farm structures, and harvested produce.',
            'Potentially Favorable Planting Conditions' => 'Forecast conditions may support land preparation or planting activities.',
            'Consider Delaying Planting Activities' => 'Excessive rainfall may make planting activities risky.',
            'Supplemental Irrigation May Be Needed' => 'Low forecast rainfall and expected water loss may reduce field moisture.',
            'Consider Postponing Irrigation' => 'Significant rainfall is expected, so irrigation may be delayed when field conditions allow.',
            default => $rule->description ?: $rule->name,
        };

        return isset($context['advisory_horizon_label'])
            ? "{$context['advisory_horizon_label']} outlook: {$summary}"
            : $summary;
    }

    private function messageFor(AdvisoryRule $rule, array $metrics, array $context = []): string
    {
        $period = trim(($metrics['forecast_start'] ?? '').' to '.($metrics['forecast_end'] ?? ''));
        $horizon = $context['advisory_horizon_description'] ?? 'forecast data';

        return $this->summaryFor($rule, $metrics, $context).' Forecast period: '.$period.'. Horizon: '.$horizon.'. This advisory is generated from live Lian, Batangas forecast data and iClimate decision-support rules.';
    }

    private function baselineMessage(array $metrics, array $context): string
    {
        $period = trim(($metrics['forecast_start'] ?? '').' to '.($metrics['forecast_end'] ?? ''));
        $label = (string) ($context['advisory_horizon_label'] ?? 'Forecast');
        $horizon = $context['advisory_horizon_description'] ?? 'forecast data';
        $rainfall = number_format((float) ($metrics['seven_day_rainfall'] ?? 0), 1);
        $probability = number_format((float) ($metrics['max_precipitation_probability'] ?? 0), 0);
        $temperature = number_format((float) ($metrics['avg_temperature_max'] ?? 0), 1);

        return "{$label} outlook: No high-risk advisory rule was triggered for Lian, Batangas. Forecast period: {$period}. Horizon: {$horizon}. Highest rain probability is about {$probability}%, estimated rainfall in the checked window totals about {$rainfall} mm, and average maximum temperature is about {$temperature} °C. Continue monitoring because local field conditions can change.";
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
        return 'This advisory is generated from forecast data and iClimate decision-support rules. Actual farm conditions may differ. Consult the Municipal Agriculture Office and follow official PAGASA online advisories or local government warnings during severe weather.';
    }

    private function generationPeriodStamp($date, string $horizon): string
    {
        return match ($horizon) {
            'hourly' => $date->copy()->startOfHour()->toDateTimeString(),
            'daily' => $date->copy()->startOfDay()->toDateTimeString(),
            'weekly' => $date->copy()->startOfWeek()->toDateTimeString(),
            'monthly' => $date->copy()->startOfMonth()->toDateTimeString(),
            default => $date->copy()->startOfDay()->toDateTimeString(),
        };
    }

    private function horizonDefinitions(): array
    {
        return [
            'hourly' => [
                'label' => 'Hourly Update',
                'days' => 1,
                'validity_hours' => 1,
                'description' => 'refreshed every hour using the latest available forecast data for Lian, Batangas',
            ],
            'daily' => [
                'label' => '24-Hour',
                'days' => 1,
                'validity_hours' => 24,
                'description' => 'next 24-hour farm advisory window for Lian, Batangas',
            ],
            'weekly' => [
                'label' => '7-Day',
                'days' => 7,
                'validity_hours' => 168,
                'description' => 'seven-day farm advisory outlook for Lian, Batangas',
            ],
            'monthly' => [
                'label' => '16-Day',
                'days' => 16,
                'validity_hours' => 720,
                'description' => 'extended outlook based on the longest available Open-Meteo forecast window for Lian, Batangas',
            ],
        ];
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
