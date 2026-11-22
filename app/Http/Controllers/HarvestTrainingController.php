<?php

namespace App\Http\Controllers;

use App\Models\FarmerPlantingSchedule;
use App\Models\RiceVariety;
use App\Support\LianBarangays;
use App\Support\RiceSeason;
use App\Support\SchemaCache;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * MAO page: "Harvest records for model training".
 * Tracks farmer-recorded harvests (actual_yield, harvested_on, farm_id, barangay, farm_type, season, rice_variety_id)
 * per barangay x farm type x season toward a retraining threshold, with an anonymized CSV export.
 */
class HarvestTrainingController extends Controller
{
    public const TARGET_PER_BUCKET = 15;

    public function index(): View
    {
        $rows = $this->harvestRows();
        $buckets = $rows
            ->groupBy(fn (array $r): string => $r['barangay'].'|'.$r['farm_type'].'|'.$r['season'])
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'barangay' => $first['barangay'],
                    'farm_type' => $first['farm_type'],
                    'season' => $first['season'],
                    'count' => $group->count(),
                    'avg_yield' => round((float) $group->avg('actual_yield'), 2),
                    'latest' => $group->max('harvested_on'),
                ];
            })
            ->sortBy([['barangay', 'asc'], ['farm_type', 'asc'], ['season', 'asc']])
            ->values();

        return view('analysis.harvest-training', [
            'total' => $rows->count(),
            'buckets' => $buckets,
            'target' => self::TARGET_PER_BUCKET,
            'readyBuckets' => $buckets->where('count', '>=', self::TARGET_PER_BUCKET)->count(),
        ]);
    }

    public function export(): StreamedResponse
    {
        $rows = $this->harvestRows();

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'farm_hash',
                'barangay',
                'farm_type',
                'season',
                'harvested_on',
                'actual_yield_t_ha',
                'rice_variety_id',
                'rice_variety_name',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row['farm_hash'],
                    $row['barangay'],
                    $row['farm_type'],
                    $row['season'],
                    $row['harvested_on'],
                    $row['actual_yield'],
                    $row['rice_variety_id'],
                    $row['rice_variety_name'],
                ]);
            }

            fclose($out);
        }, 'harvest-training-records-'.now('Asia/Manila')->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function harvestRows(): Collection
    {
        if (! SchemaCache::hasColumn('farmer_planting_schedules', 'actual_yield')) {
            return collect();
        }

        $varieties = SchemaCache::hasTable('rice_varieties')
            ? RiceVariety::query()->pluck('variety_name', 'id')
            : collect();

        return FarmerPlantingSchedule::query()
            ->whereNotNull('actual_yield')
            ->where('actual_yield', '>', 0)
            ->orderByDesc('harvested_on')
            ->orderByDesc('id')
            ->get([
                'id', 'user_id', 'farm_id', 'barangay', 'farm_type', 'season',
                'actual_yield', 'harvested_on', 'planting_date', 'rice_variety_id',
            ])
            ->map(function (FarmerPlantingSchedule $s) use ($varieties): array {
                $barangay = LianBarangays::canonicalName($s->barangay) ?: ($s->barangay ?: 'Unknown');
                $farmType = $s->farm_type ?: 'Irrigated';
                $season = $s->season ?: ($s->planting_date ? RiceSeason::forDate($s->planting_date) : 'Wet');
                $seed = $s->farm_id ? 'farm:'.$s->farm_id : 'user:'.$s->user_id;

                return [
                    'farm_hash' => substr(hash_hmac('sha256', $seed, (string) config('app.key')), 0, 12),
                    'barangay' => $barangay,
                    'farm_type' => $farmType,
                    'season' => $season,
                    'harvested_on' => $s->harvested_on?->toDateString() ?? '',
                    'actual_yield' => round((float) $s->actual_yield, 2),
                    'rice_variety_id' => $s->rice_variety_id,
                    'rice_variety_name' => $s->rice_variety_id ? ($varieties->get($s->rice_variety_id) ?? '') : '',
                ];
            });
    }
}
