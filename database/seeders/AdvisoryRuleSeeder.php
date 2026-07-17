<?php

namespace Database\Seeders;

use App\Models\AdvisoryRule;
use Illuminate\Database\Seeder;

class AdvisoryRuleSeeder extends Seeder
{
    public function run(): void
    {
        collect($this->rules())->each(function (array $rule): void {
            AdvisoryRule::query()->updateOrCreate(
                ['name' => $rule['name']],
                $rule,
            );
        });
    }

    private function rules(): array
    {
        return [
            [
                'name' => 'Heavy Rainfall Warning',
                'advisory_type' => 'climate',
                'description' => 'Heavy rainfall expected within 24 hours.',
                'severity' => 'high',
                'priority' => 80,
                'conditions' => ['daily_precipitation_probability_min' => 80, 'daily_precipitation_sum_min' => 30, 'validity_hours' => 24, 'requires_review' => false],
                'recommendation' => 'Clear drainage canals, secure farm tools and inputs, monitor low-lying fields, and follow official local government or PAGASA warnings.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo forecast variables: precipitation_probability_max, precipitation_sum',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Severe Rainfall and Flooding Risk',
                'advisory_type' => 'climate',
                'description' => 'Extreme rainfall or consecutive heavy-rain days.',
                'severity' => 'critical',
                'priority' => 100,
                'conditions' => ['daily_precipitation_sum_min' => 50, 'consecutive_days_precipitation_sum_min' => 30, 'consecutive_days' => 2, 'validity_hours' => 24, 'requires_review' => true],
                'recommendation' => 'Delay field activities, protect stored farm inputs, inspect drainage systems, and monitor official emergency advisories.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo forecast variables: precipitation_sum',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'High Temperature Advisory',
                'advisory_type' => 'climate',
                'description' => 'Daily maximum temperature reaches heat-stress range.',
                'severity' => 'moderate',
                'priority' => 60,
                'conditions' => ['temperature_max_min' => 35, 'validity_hours' => 24, 'requires_review' => false],
                'recommendation' => 'Schedule field work during cooler hours, maintain hydration, monitor livestock, and check soil moisture.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo forecast variable: temperature_2m_max',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Strong Wind Advisory',
                'advisory_type' => 'climate',
                'description' => 'Strong wind may affect farm structures and seedlings.',
                'severity' => 'high',
                'priority' => 75,
                'conditions' => ['wind_speed_max_min' => 35, 'validity_hours' => 24, 'requires_review' => false],
                'recommendation' => 'Secure lightweight farm structures, tools, seedlings, and harvested produce.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo forecast variable: wind_speed_10m_max',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Potentially Favorable Planting Conditions',
                'advisory_type' => 'planting',
                'description' => 'Seven-day rainfall and temperature may support land preparation or planting.',
                'severity' => 'information',
                'priority' => 35,
                'conditions' => ['seven_day_rainfall_between' => [20, 70], 'avg_temperature_max_between' => [25, 33], 'probability_days_min' => 2, 'probability_min' => 40, 'validity_hours' => 72, 'requires_review' => false],
                'recommendation' => 'Inspect field drainage, confirm soil moisture, prepare seeds, and consult the Municipal Agriculture Office before planting.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo seven-day rainfall and temperature forecast',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Consider Delaying Planting Activities',
                'advisory_type' => 'planting',
                'description' => 'Excessive rainfall may make planting risky in flood-prone fields.',
                'severity' => 'high',
                'priority' => 85,
                'conditions' => ['seven_day_rainfall_min' => 100, 'critical_rainfall_advisory' => true, 'validity_hours' => 72, 'requires_review' => false],
                'recommendation' => 'Delay planting in flood-prone fields until rainfall decreases and field drainage becomes manageable.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo seven-day rainfall forecast',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Supplemental Irrigation May Be Needed',
                'advisory_type' => 'irrigation',
                'description' => 'Low rainfall and evapotranspiration may reduce field moisture.',
                'severity' => 'moderate',
                'priority' => 65,
                'conditions' => ['three_day_rainfall_max' => 5, 'avg_evapotranspiration_min' => 4, 'soil_moisture_max' => 0.18, 'validity_hours' => 48, 'requires_review' => false],
                'recommendation' => 'Inspect actual field water level and soil condition. Apply supplemental irrigation only when needed and avoid unnecessary water use.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo rainfall, soil moisture, and evapotranspiration forecast',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Consider Postponing Irrigation',
                'advisory_type' => 'irrigation',
                'description' => 'Significant rainfall is expected soon.',
                'severity' => 'information',
                'priority' => 40,
                'conditions' => ['next_24h_rainfall_min' => 15, 'next_24h_probability_min' => 80, 'validity_hours' => 24, 'requires_review' => false],
                'recommendation' => 'Delay irrigation when field conditions allow because significant rainfall is expected.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo 24-hour rainfall and probability forecast',
                'is_active' => true,
                'requires_crop_data' => false,
            ],
            [
                'name' => 'Consider Harvesting Before Expected Rainfall',
                'advisory_type' => 'harvesting',
                'description' => 'Harvest-ready crop data is required before this rule can generate advisories.',
                'severity' => 'high',
                'priority' => 85,
                'conditions' => ['harvest_ready_required' => true, 'next_48h_probability_min' => 70, 'next_48h_rainfall_min' => 15, 'validity_hours' => 48, 'requires_review' => true],
                'recommendation' => 'Prioritize harvest-ready fields when safe and prepare drying or protected storage facilities.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo 48-hour rainfall forecast plus crop-stage data',
                'is_active' => true,
                'requires_crop_data' => true,
            ],
            [
                'name' => 'Delay Harvesting During Hazardous Weather',
                'advisory_type' => 'harvesting',
                'description' => 'Harvest-ready crop data is required before this rule can generate advisories.',
                'severity' => 'high',
                'priority' => 90,
                'conditions' => ['harvest_ready_required' => true, 'heavy_rainfall_required' => true, 'wind_speed_max_min' => 35, 'validity_hours' => 24, 'requires_review' => true],
                'recommendation' => 'Resume harvesting only when field and weather conditions are safe.',
                'source_name' => 'Open-Meteo + iClimate Rules',
                'source_reference' => 'Open-Meteo rainfall and wind forecast plus crop-stage data',
                'is_active' => true,
                'requires_crop_data' => true,
            ],
        ];
    }
}
