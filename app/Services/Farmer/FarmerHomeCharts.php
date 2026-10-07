<?php

namespace App\Services\Farmer;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Weather\DailyForecastService;
use App\Support\RiceSeason;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Farmer Home's two simple charts (2026-10, Farmer – Naicel), from saved data only:
 *   - harvest:  the farmer's recorded harvest per season (t/ha) as bars, the barangay average as a line;
 *   - weather:  the next 14 days' highest temperature (°C) and chance of rain (%), from the stored daily forecast.
 * With nothing to draw a chart says so in one short line (state "nodata") instead of showing an empty chart.
 * No provider is called and no model is run.
 */
class FarmerHomeCharts
{
    /** How many seasons the harvest chart shows at most (the latest ones). */
    public const MAX_SEASONS = 8;

    /** A barangay average from farmers' own records is shown only with this many farmers, so no one's harvest can be read from it. */
    public const MIN_FARMERS_FOR_AVERAGE = 3;

    public const FORECAST_DAYS = 14;

    public function __construct(private readonly DailyForecastService $daily) {}

    /**
     * @param  ?Farm  $farm  with two or more farms: the farm being viewed (the My Farm selector, ActiveFarm); otherwise null (all of the farmer's records)
     * @return array{harvest: array<string, mixed>, weather: array<string, mixed>}
     */
    public function for(User $user, ?Farm $farm, ?string $barangay): array
    {
        return [
            'harvest' => rescue(fn (): array => $this->harvest($user, $farm, $barangay), ['state' => 'nodata'], true),
            'weather' => rescue(fn (): array => $this->weather($barangay), ['state' => 'nodata'], true),
        ];
    }

    /** @return array<string, mixed> state: ok | nodata */
    public function harvest(User $user, ?Farm $farm, ?string $barangay): array
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return ['state' => 'nodata'];
        }

        $seasons = FarmerPlantingSchedule::query()
            ->where('user_id', $user->id)
            // One farm's records; plans older than farms (no farm_id) count as the primary farm's, as on My Farm.
            ->when($farm !== null && SchemaCache::hasColumn('farmer_planting_schedules', 'farm_id'), fn ($q) => $q->where(fn ($w) => $w->where('farm_id', $farm->getKey())
                ->when($farm->is_primary, fn ($p) => $p->orWhereNull('farm_id'))))
            ->where('actual_yield', '>', 0)
            ->get()
            ->groupBy(fn (FarmerPlantingSchedule $s): string => $this->seasonKey($s))
            ->sortKeys()
            ->slice(-self::MAX_SEASONS);
        if ($seasons->isEmpty()) {
            return ['state' => 'nodata'];
        }

        $labels = [];
        $mine = [];
        $average = [];
        foreach ($seasons as $key => $rows) {
            [$year, $order] = array_map('intval', explode('-', $key));
            $season = $order === 1 ? RiceSeason::WET : RiceSeason::DRY;
            $labels[] = __('farmer.charts.season.'.strtolower($season), ['year' => $year, 'years' => $year.'–'.substr((string) ($year + 1), -2)]);
            $mine[] = round((float) $rows->avg(fn (FarmerPlantingSchedule $s): float => (float) $s->actual_yield), 2);
            $average[] = $barangay ? $this->barangayAverage($barangay, $season, $year) : null;
        }

        return [
            'state' => 'ok',
            'labels' => $labels,
            'mine' => $mine,
            'average' => $average,
            'has_average' => array_filter($average, fn ($v): bool => $v !== null) !== [],
            'barangay' => $barangay,
        ];
    }

    /** "2026-1" (wet season that starts in 2026) or "2026-2" (dry season that starts in November 2026): sorts by time. */
    private function seasonKey(FarmerPlantingSchedule $schedule): string
    {
        $window = RiceSeason::window($schedule->planting_date);

        return $window['start']->year.'-'.($window['season'] === RiceSeason::WET ? 1 : 2);
    }

    /**
     * The barangay's usual yield for one season: the MAO's rice production records when there are any, otherwise the
     * average of farmers' own recorded harvests — only when at least MIN_FARMERS_FOR_AVERAGE farmers have one.
     */
    private function barangayAverage(string $barangay, string $season, int $year): ?float
    {
        try {
            $years = $season === RiceSeason::WET ? [$year] : [$year, $year + 1];
            $recorded = SampleData::without(RiceProduction::query(), 'rice_productions')
                ->where('barangay', $barangay)->where('season', $season)->whereIn('year', $years)
                ->whereNotNull('yield_per_hectare')->where('yield_per_hectare', '>', 0)
                ->avg('yield_per_hectare');
            if ($recorded !== null) {
                return round((float) $recorded, 2);
            }

            $start = $season === RiceSeason::WET ? CarbonImmutable::create($year, 5, 1) : CarbonImmutable::create($year, 11, 1);
            $end = $season === RiceSeason::WET ? CarbonImmutable::create($year, 10, 31) : CarbonImmutable::create($year + 1, 4, 30);
            /** @var Collection<int, FarmerPlantingSchedule> $rows */
            $rows = FarmerPlantingSchedule::query()
                ->where('barangay', $barangay)
                ->where('actual_yield', '>', 0)
                ->whereDate('planting_date', '>=', $start->toDateString())
                ->whereDate('planting_date', '<=', $end->toDateString())
                ->get(['user_id', 'actual_yield']);
            if ($rows->pluck('user_id')->unique()->count() < self::MIN_FARMERS_FOR_AVERAGE) {
                return null;
            }

            return round((float) $rows->avg(fn (FarmerPlantingSchedule $s): float => (float) $s->actual_yield), 2);
        } catch (Throwable $e) {
            Log::warning('Farmer Home barangay harvest average failed.', ['barangay' => $barangay, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /** @return array<string, mixed> state: ok | nodata */
    public function weather(?string $barangay): array
    {
        return $this->weatherFrom($this->daily->forLocation($barangay, self::FORECAST_DAYS));
    }

    /**
     * @param  array<string, mixed>  $forecast  what DailyForecastService::forLocation() returns
     * @return array<string, mixed> state: ok | nodata
     */
    public function weatherFrom(array $forecast): array
    {
        if (($forecast['state'] ?? null) !== 'ok') {
            return ['state' => 'nodata'];
        }

        $labels = [];
        $temperature = [];
        $rain = [];
        foreach ($forecast['days'] as $i => $day) {
            $labels[] = $i === 0 ? __('farmer.predictions.today') : $day['date']->locale(app()->getLocale())->translatedFormat('D j');
            $high = $day['high'] ?? $day['avg'] ?? null;
            $temperature[] = is_numeric($high) ? round((float) $high) : null;
            $rain[] = is_numeric($day['chance'] ?? null) ? round((float) $day['chance']) : null;
        }
        // A chart needs a line: at least two days with a temperature.
        if (count(array_filter($temperature, fn ($v): bool => $v !== null)) < 2) {
            return ['state' => 'nodata'];
        }

        return [
            'state' => 'ok',
            'labels' => $labels,
            'temperature' => $temperature,
            'rain' => $rain,
            'has_rain' => array_filter($rain, fn ($v): bool => $v !== null) !== [],
            'location' => $forecast['location'],
        ];
    }
}
