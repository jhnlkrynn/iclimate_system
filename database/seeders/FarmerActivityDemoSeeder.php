<?php

namespace Database\Seeders;

use App\Models\Farm;
use App\Models\FarmerPlantingSchedule;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Support\HarvestTiming;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Activity-only demo data for FarmerDemoSeeder accounts. */
class FarmerActivityDemoSeeder extends Seeder
{
    private const SOURCE = 'demo_seed';
    private const TODAY = '2026-10-18';
    private const BATCH = 500;
    private array $scheduleIds = [], $farmIds = [], $productionIds = [], $conflicts = [], $seasonReport = [];
    private array $counts = ['schedules_created' => 0, 'schedules_updated' => 0, 'farms_created' => 0, 'productions_created' => 0];

    public function run(bool $reset = false, bool $allowRemote = false): array
    {
        if (app()->environment('production')) throw new \RuntimeException('Refusing to seed demo activity while APP_ENV=production.');
        $connection = (string) config('database.default');
        $host = (string) config("database.connections.{$connection}.host", '');
        if (! $allowRemote && $connection !== 'sqlite' && $host !== '' && ! in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException("Refusing to seed non-local database host {$host}. Set the local database connection first.");
        }
        if (! Schema::hasTable('farmer_planting_schedules')) throw new \RuntimeException('farmer_planting_schedules is not available.');
        $this->backup();
        $report = DB::transaction(function () use ($reset): array {
            $users = User::query()->where('role', User::ROLE_FARMER)->where('email', 'like', '%@'.FarmerDemoSeeder::EMAIL_DOMAIN)->orderBy('id')->get();
            if ($users->isEmpty()) throw new \RuntimeException('No seeded @'.FarmerDemoSeeder::EMAIL_DOMAIN.' farmer accounts were found.');
            $ids = $users->pluck('id')->all();
            $this->removeOldActivity($ids, $reset);
            $farms = $this->ensureFarms($users);
            $rows = [];
            foreach ($users as $user) foreach ($farms->get($user->id, collect()) as $farm) foreach ($this->farmerRows($user, $farm) as $row) $rows[] = $row;
            $this->rescaleOfficialRows($rows);
            foreach (array_chunk($rows, self::BATCH) as $batch) $this->writeSchedules($batch);
            $this->refreshSeasonReport($ids);
            $this->writeOfficialProductionRows($farms);
            $this->writeIds();
            return $this->counts + [
                'seeded_farmers' => $users->count(), 'farmers_with_farm' => $farms->filter(fn ($r) => $r->isNotEmpty())->count(),
                'farmers_with_schedule' => FarmerPlantingSchedule::query()->whereIn('user_id', $ids)->where('prediction_source', self::SOURCE)->distinct('user_id')->count('user_id'),
                'schedule_ids' => count($this->scheduleIds), 'season_report' => $this->seasonReport, 'conflicts' => $this->conflicts,
            ];
        });
        Storage::disk('local')->put('demo/activity-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $report;
    }

    private function backup(): void
    {
        $ids = User::query()->where('email', 'like', '%@'.FarmerDemoSeeder::EMAIL_DOMAIN)->pluck('id');
        $disk = Storage::disk('local'); $disk->makeDirectory('demo');
        $payload = ['created_at' => Carbon::now('Asia/Manila')->toIso8601String(), 'scope' => 'seeded @iclimate.test farmers; rice_productions is full-table because it has no farmer foreign key',
            'farmer_planting_schedules' => DB::table('farmer_planting_schedules')->whereIn('user_id', $ids)->get()->map(fn ($r) => (array) $r)->all(),
            'farms' => Schema::hasTable('farms') ? DB::table('farms')->whereIn('user_id', $ids)->get()->map(fn ($r) => (array) $r)->all() : [],
            'rice_productions' => Schema::hasTable('rice_productions') ? DB::table('rice_productions')->get()->map(fn ($r) => (array) $r)->all() : []];
        $stamp = Carbon::now('Asia/Manila')->format('Ymd-His');
        $disk->put('demo/backup-'.$stamp.'.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function removeOldActivity(array $userIds, bool $reset): void
    {
        $path = Storage::disk('local')->path('demo/seeded-activity-ids.json');
        $manifest = is_file($path) ? (json_decode((string) file_get_contents($path), true) ?: []) : [];
        $scheduleIds = array_values(array_filter(array_map('intval', $manifest['farmer_planting_schedules'] ?? [])));
        if ($scheduleIds !== []) FarmerPlantingSchedule::query()->whereIn('id', $scheduleIds)->delete();
        $productionIds = array_values(array_filter(array_map('intval', $manifest['rice_productions'] ?? [])));
        if ($productionIds !== [] && Schema::hasTable('rice_productions')) RiceProduction::query()->whereIn('id', $productionIds)->delete();
        if ($scheduleIds === [] || ! $reset) FarmerPlantingSchedule::query()->whereIn('user_id', $userIds)->where('prediction_source', self::SOURCE)->delete();
    }

    private function ensureFarms($users): \Illuminate\Support\Collection
    {
        $result = collect();
        foreach ($users as $user) {
            $profile = FarmerProfile::query()->where('user_id', $user->id)->first();
            $farms = Farm::query()->where('user_id', $user->id)->whereNull('archived_at')->orderBy('id')->get();
            if ($farms->isEmpty()) {
                $farm = Farm::query()->create(['user_id' => $user->id, 'farm_name' => 'Farm 1', 'barangay' => $profile?->barangay ?: $user->barangay,
                    'location_description' => ($profile?->barangay ?: $user->barangay ?: 'Lian').', Lian, Batangas', 'location_method' => Farm::LOCATION_BARANGAY,
                    'farm_size_hectares' => max(.25, (float) ($profile?->farm_area ?: 1)), 'farm_size_method' => Farm::SIZE_EXACT, 'farm_size_unit' => Farm::UNIT_HECTARES,
                    'farm_type' => $profile?->farm_type ?: FarmerProfile::FARM_TYPE_IRRIGATED, 'is_primary' => true]);
                $farms = collect([$farm]); $this->farmIds[] = $farm->id; $this->counts['farms_created']++;
            }
            foreach ($farms as $index => $farm) if (preg_match('/^(?:Demo )?Rice Farm \d+$/i', (string) $farm->farm_name) || $farm->farm_name === null) $farm->update(['farm_name' => 'Farm '.($index + 1)]);
            $result->put($user->id, $farms);
        }
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    private function farmerRows(User $user, Farm $farm): array
    {
        $key = $user->id * 37 + $farm->id * 19;
        $varieties = ['NSIC Rc 222' => 114, 'Rc 216' => 112, 'Rc 160' => 122, 'Rc 402' => 110, 'Rc 480' => 115, 'Rc 506' => 110, 'SL-8H' => 123, 'Mestiso 20' => 110];
        $variety = array_keys($varieties)[$key % count($varieties)]; $maturity = $varieties[$variety]; $factor = .85 + (($user->id * 13) % 31) / 100; $rows = [];
        foreach ($this->officialRows()->groupBy('season_label') as $label => $months) {
            $months = collect($months); $targetArea = (float) $months->sum('area_ha'); $monthRow = $months->sortBy('month')->values()[$key % $months->count()];
            $area = $this->allocatedArea($farm, $targetArea); if ($area === null) continue;
            $plant = $this->dateInMonth($monthRow['month'], $key + $user->id); $expected = $plant->copy()->addDays($maturity);
            $isTyphoon = str_contains((string) $monthRow['remark'], 'Typhoon'); $offset = $isTyphoon ? (($key % 3 === 0) ? -8 : 7) : (($key % 9) - 4); $harvest = $expected->copy()->addDays($offset);
            $yield = (float) $monthRow['avg_yield'] * $factor * (1 + (($key % 7) - 3) / 100);
            $rows[] = $this->scheduleRow($user, $farm, $plant, $expected, $harvest, $yield, $variety, $maturity, $label, $monthRow['season'], $area, $isTyphoon ? 'typhoon' : null, $monthRow['remark']);
        }
        return array_merge($rows, $this->projectedRows($user, $farm, $key, $variety, $maturity, $factor));
    }

    private function projectedRows(User $user, Farm $farm, int $key, string $variety, int $maturity, float $factor): array
    {
        $area = (float) $farm->farm_size_hectares; $month = match (true) { $key % 5 === 0 => 5, $key % 5 === 1 => 6, $key % 5 === 2 => 7, $key % 5 === 3 => 8, default => 9 };
        $plant = Carbon::create(2026, $month, 10 + ($key % 18), 0, 0, 0, 'Asia/Manila'); $expected = $plant->copy()->addDays($maturity);
        $harvest = $month <= 6 || ($month === 7 && $key % 2 === 0) ? $expected->copy()->addDays(($key % 7) - 3) : null;
        $rows = [];
        $ds2526Plant = Carbon::create(2025, 10, 10 + ($key % 18), 0, 0, 0, 'Asia/Manila');
        $ds2526Expected = $ds2526Plant->copy()->addDays($maturity);
        $rows[] = $this->scheduleRow($user, $farm, $ds2526Plant, $ds2526Expected, $ds2526Expected->copy()->addDays(($key % 7) - 3), 4.0 * $factor, $variety, $maturity, 'DS 2025-26 projection', 'Dry', min($area * .886, $area), null, 'Projection; completed DS 2025-26');
        $rows[] = $this->scheduleRow($user, $farm, $plant, $expected, $harvest, 3.8 * $factor, $variety, $maturity, 'WS 2026 projection', 'Wet', $area, null, 'Projection; current Wet 2026 crop'.($harvest ? '' : ' growing/maturing'));
        if ($key % 10 < 1) {
            $plant = Carbon::create(2026, 10, 1 + ($key % 17), 0, 0, 0, 'Asia/Manila'); $rows[] = $this->scheduleRow($user, $farm, $plant, $plant->copy()->addDays($maturity), null, 4.0 * $factor, $variety, $maturity, 'DS 2026-27 projection', 'Dry', min($area * .1, $area), null, 'Projection; already planted in October 2026');
        } elseif ($key % 2 === 0) {
            $plant = Carbon::create(2026, 11 + ($key % 2), 1 + ($key % 18), 0, 0, 0, 'Asia/Manila'); $rows[] = $this->scheduleRow($user, $farm, $plant, $plant->copy()->addDays($maturity), null, 4.0 * $factor, $variety, $maturity, 'DS 2026-27 projection', 'Dry', min($area * .5, $area), null, 'Projection; planned planting');
        }
        return $rows;
    }

    private function scheduleRow(User $user, Farm $farm, Carbon $plant, Carbon $expected, ?Carbon $harvest, float $yield, string $variety, int $maturity, string $label, string $season, float $area, ?string $reason, string $remark): array
    {
        $timing = $harvest ? HarvestTiming::classify($expected, $harvest) : null; $timingReason = $timing === HarvestTiming::EARLY || $timing === HarvestTiming::LATE ? ($reason ?: 'labor_machinery') : null;
        $damaged = $reason === 'typhoon' && str_contains($label, '2024'); $status = $harvest ? ($damaged ? 'Needs Review' : 'Harvested') : 'Planted';
        return ['user_id' => $user->id, 'farm_id' => $farm->id, 'planting_date' => $plant->toDateString(), 'scheduled_harvest_date' => $expected->toDateString(), 'harvest_month' => $expected->copy()->startOfMonth()->toDateString(),
            'season' => $season, 'farm_type' => $farm->farm_type ?: 'Rainfed', 'barangay' => $farm->barangay, 'farm_area_hectares' => round(min((float) $farm->farm_size_hectares, $area), 2),
            'notes' => $label.'. Variety: '.$variety.' ('.$maturity.' days). Method: '.($user->id % 4 === 0 ? 'direct seeding' : 'transplanting').'. Complete fertilizer at 21 days; urea at 45 days. '.$remark,
            'progress_status' => $status, 'predicted_yield' => round($yield + .12, 2), 'prediction_confidence' => $harvest ? 86 : 78, 'prediction_source' => self::SOURCE,
            'actual_yield' => $harvest ? round($damaged ? min($yield, 1.8) : $yield, 2) : null, 'harvested_on' => $harvest?->toDateString(), 'harvest_timing' => $timing, 'harvest_timing_reason' => $timingReason, 'harvest_timing_reason_other' => null,
            'created_at' => $plant, 'updated_at' => $harvest ?: $plant];
    }

    private function officialRows(): \Illuminate\Support\Collection
    {
        $path = base_path('database/seeders/data/mao-planting-summary-2020-2025.csv'); if (! is_file($path)) throw new \RuntimeException('Official planting summary CSV not found.');
        $handle = fopen($path, 'rb'); $header = fgetcsv($handle); $rows = [];
        while (($line = fgetcsv($handle)) !== false) { if (count($line) < count($header)) continue; $row = array_combine($header, $line); foreach (['area_ha', 'production_t', 'avg_yield'] as $f) $row[$f] = (float) $row[$f]; $rows[] = $row; }
        fclose($handle); return collect($rows);
    }

    private function dateInMonth(string $month, int $seed): Carbon
    {
        $date = Carbon::createFromFormat('Y-m-d', $month.'-01', 'Asia/Manila'); return $date->addDays(5 + ($seed % max(1, $date->daysInMonth - 10)));
    }

    private function allocatedArea(Farm $farm, float $target): ?float
    {
        $total = (float) $farm->farm_size_hectares; if ($target <= 0 || $total <= 0) return null; return min($total, max(.1, round($total * min(1, $target / 733.56), 2)));
    }

    private function writeSchedules(array $rows): void
    {
        foreach ($rows as $attributes) { $existing = FarmerPlantingSchedule::query()->where('user_id', $attributes['user_id'])->where('farm_id', $attributes['farm_id'])->whereDate('planting_date', $attributes['planting_date'])->first(); $model = $existing ?: new FarmerPlantingSchedule; $model->fill($attributes); $model->created_at = $attributes['created_at']; $model->updated_at = $attributes['updated_at']; $model->save(); $this->scheduleIds[] = $model->id; $this->counts[$existing ? 'schedules_updated' : 'schedules_created']++; }
    }

    private function rescaleOfficialRows(array &$rows): void
    {
        $targets = $this->officialRows()->groupBy('season_label')->map(fn ($r) => (float) collect($r)->sum('production_t'));
        foreach ($targets as $label => $target) {
            $matching = collect($rows)->filter(fn (array $row): bool => str_starts_with((string) $row['notes'], $label.'.'));
            $raw = (float) $matching->sum(fn (array $row): float => (float) $row['farm_area_hectares'] * (float) ($row['actual_yield'] ?? 0));
            if ($raw <= 0) continue;
            $scale = $target / $raw;
            foreach ($rows as &$row) {
                if (! str_starts_with((string) $row['notes'], $label.'.') || $row['actual_yield'] === null) continue;
                $row['actual_yield'] = round((float) $row['actual_yield'] * $scale, 2);
                $row['predicted_yield'] = round((float) $row['actual_yield'] + .12, 2);
            }
            unset($row);
        }
    }

    private function refreshSeasonReport(array $userIds): void
    {
        $this->seasonReport = $this->officialRows()->groupBy('season_label')->map(fn ($r): array => ['official_area' => round((float) collect($r)->sum('area_ha'), 2), 'official_production' => round((float) collect($r)->sum('production_t'), 2)])->all();
        $rows = FarmerPlantingSchedule::query()->whereIn('user_id', $userIds)->where('prediction_source', self::SOURCE)->get();
        foreach ($rows->groupBy(fn (FarmerPlantingSchedule $s): string => trim(strtok((string) $s->notes, '.'))) as $label => $group) {
            $this->seasonReport[$label] = ($this->seasonReport[$label] ?? []) + ['seeded_area' => round((float) $group->sum('farm_area_hectares'), 2), 'seeded_production' => round((float) $group->sum(fn ($s) => (float) $s->farm_area_hectares * (float) ($s->actual_yield ?? 0)), 2), 'farmers_planting' => $group->pluck('user_id')->unique()->count(), 'harvested' => $group->whereNotNull('harvested_on')->count(), 'growing' => $group->whereNull('harvested_on')->filter(fn ($s) => $s->planting_date <= Carbon::parse(self::TODAY))->count(), 'planned' => $group->whereNull('harvested_on')->filter(fn ($s) => $s->planting_date > Carbon::parse(self::TODAY))->count()];
        }
    }

    private function writeOfficialProductionRows($farms): void
    {
        if (! Schema::hasTable('rice_productions')) return;
        $official = $this->officialRows(); $weights = $farms->flatten()->groupBy(fn (Farm $f) => (string) $f->barangay)->map(fn ($r) => (float) $r->sum(fn (Farm $f) => (float) $f->farm_size_hectares)); $totalWeight = max(1, (float) $weights->sum());
        foreach ($official->groupBy('season_label') as $label => $monthRows) {
            $monthRows = collect($monthRows); $season = (string) $monthRows->first()['season']; $year = (int) substr((string) $monthRows->first()['month'], 0, 4); $area = (float) $monthRows->sum('area_ha'); $production = (float) $monthRows->sum('production_t');
            foreach ($weights as $barangay => $weight) { $share = (float) $weight / $totalWeight; $expectedArea = round($area * $share, 2); $expectedProduction = round($production * $share, 2); $existing = RiceProduction::query()->where('barangay', $barangay)->where('season', $season)->where('year', $year)->where('irrigation_type', 'Irrigated')->first();
                if ($existing) { if (abs((float) $existing->area_hectares - $expectedArea) > .02 || abs((float) $existing->total_production - $expectedProduction) > .02) $this->conflicts[] = ['barangay' => $barangay, 'season' => $season, 'year' => $year, 'existing_area' => $existing->area_hectares, 'official_allocated_area' => $expectedArea, 'existing_production' => $existing->total_production, 'official_allocated_production' => $expectedProduction]; continue; }
                $remarks = 'Official MAO planting summary 2020-2025; '.($monthRows->pluck('remark')->filter()->unique()->implode('; ') ?: 'no special remark').'.'; $row = RiceProduction::query()->create(['barangay' => $barangay, 'season' => $season, 'irrigation_type' => 'Irrigated', 'yield_per_hectare' => $expectedArea ? round($expectedProduction / $expectedArea, 2) : null, 'area_hectares' => $expectedArea, 'area_harvested_hectares' => $expectedArea, 'total_production' => $expectedProduction, 'year' => $year, 'production_month' => $monthRows->sortBy('month')->first()['month'].'-01', 'remarks' => $remarks]); $this->productionIds[] = $row->id; $this->counts['productions_created']++;
            }
            // Keep the schedule-derived seeded totals already computed by
            // refreshSeasonReport(); inserting an aggregate must not erase
            // the season table's activity figures.
        }
    }

    private function writeIds(): void
    { Storage::disk('local')->makeDirectory('demo'); Storage::disk('local')->put('demo/seeded-activity-ids.json', json_encode(['generated_at' => Carbon::now('Asia/Manila')->toIso8601String(), 'farmer_planting_schedules' => array_values(array_unique($this->scheduleIds)), 'farms' => array_values(array_unique($this->farmIds)), 'rice_productions' => array_values(array_unique($this->productionIds))], JSON_PRETTY_PRINT)); }
}
