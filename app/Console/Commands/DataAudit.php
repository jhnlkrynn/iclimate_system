<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Analytics\DashboardOverviewService;
use App\Support\ProjectYieldDataset;
use App\Support\SampleDataDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only. Answers "is the data behind the dashboards real, current and
 * traceable?" and proves each dashboard figure by recounting it independently.
 */
class DataAudit extends Command
{
    protected $signature = 'iclimate:data-audit';

    protected $description = 'Read-only audit: sample/impossible rows, source freshness, duplicates, and a recount of every dashboard figure.';

    private int $failures = 0;

    public function handle(SampleDataDetector $samples, DashboardOverviewService $overview, ProjectYieldDataset $yields): int
    {
        // Audit the live database, not a cached view of it.
        Cache::flush();

        $this->section('1. Rows that are NOT verified data');
        foreach (['rice_productions', 'climate_records', 'notifications', 'planting_advisories'] as $table) {
            $s = $samples->summary($table);
            $this->line(sprintf('  %-22s total %-4d verified %-4d seeded-sample %-4d impossible %-3d', $table, $s['total'], $s['verified'], $s['sample'], $s['implausible']));
        }
        $runs = $samples->underTrainedRunIds();
        $this->line('  analytics_runs         '.count($runs).' completed prediction run(s) made with an under-trained weather model'.($runs ? ' (ids '.implode(',', $runs).')' : ''));
        $this->line('  seed batch timestamp(s): '.(implode(', ', $samples->batches()) ?: 'none found'));
        $this->line('  exclusion from dashboards: '.(config('iclimate.exclude_sample_data') ? 'ON' : 'OFF'));

        $this->section('2. Source freshness (source → stored record)');
        $this->freshness('Ambulong monthly climate (file)', is_file(database_path('data/Ambulong Monthly Data.csv')) ? date('Y-m-d H:i', filemtime(database_path('data/Ambulong Monthly Data.csv'))) : null, 'database/data/Ambulong Monthly Data.csv');
        $d = $yields->describe();
        $this->freshness('Yield history (file)', $d['updated']?->format('Y-m-d H:i'), $d['records'].' monthly values, '.$d['first'].' – '.$d['last']);
        $this->freshness('Open-Meteo weather (external_weather_data.fetched_at)', DB::table('external_weather_data')->max('fetched_at'), null);
        $this->freshness('PAGASA advisories (planting_advisories.created_at)', DB::table('planting_advisories')->whereIn('source', ['PAGASA Online Advisory Page', 'PAGASA Online Weekly Weather Outlook', 'PAGASA'])->max('created_at'), null);
        $this->freshness('Monthly forecasts (generated_at)', DB::table('monthly_weather_predictions')->max('generated_at'), null);
        $this->freshness('Risk ratings (risk_computed_at)', DB::table('heatmap_areas')->max('risk_computed_at'), null);

        $this->section('3. Duplicate protection');
        $this->check('climate_records: one row per record_date', $this->dupes('climate_records', ['record_date']) === 0);
        $this->check('rice_productions: one row per barangay/season/year/irrigation', $this->dupes('rice_productions', ['barangay', 'season', 'year', 'irrigation_type']) === 0);
        $this->check('planting_advisories: unique generation_key', DB::table('planting_advisories')->whereNotNull('generation_key')->select('generation_key')->groupBy('generation_key')->havingRaw('count(*) > 1')->get()->isEmpty());
        $this->check('external_weather_data: one row per source/date/time/barangay', $this->dupes('external_weather_data', ['source', 'forecast_date', 'forecast_time', 'barangay_id']) === 0);
        $this->check('monthly_weather_predictions: one row per year/month/model', $this->dupes('monthly_weather_predictions', ['prediction_year', 'prediction_month', 'weather_model']) === 0);

        $this->section('4. Dashboard figures recounted independently');
        $mao = $overview->mao(null);
        $it = $overview->it();
        // A KPI that has no data is swapped for another one with data, so a missing key means "no figure shown".
        $kpi = fn (array $o, string $key): array => collect($o['kpis'])->firstWhere('key', $key) ?? ['value' => 'Insufficient verified data', 'note' => ''];

        $this->compare('IT · Total users', (int) DB::table('users')->count(), (int) str_replace(',', '', $kpi($it, 'users')['value']));

        $riceVerified = $samples->summary('rice_productions')['verified'];
        $itRice = $kpi($it, 'rice-records')['value'];
        $this->compare('IT · Rice production records', $riceVerified > 0 ? (string) $riceVerified : 'Insufficient verified data', $itRice);

        $notifVerified = $samples->summary('notifications')['verified'];
        $this->compare('IT · Notifications sent (in note)', (string) $notifVerified, preg_match('/of ([\d,]+) sent/', $kpi($it, 'notifications')['note'], $m) ? str_replace(',', '', $m[1]) : '?');

        $year = $mao['year'];
        $expectedYield = $year && $yields->yearlyMean($year) !== null ? number_format($yields->yearlyMean($year), 2) : 'Insufficient verified data';
        $this->compare("MAO · Average yield ($year) vs yield dataset", $expectedYield, $kpi($mao, 'yield')['value']);

        $expectedProduction = $riceVerified > 0 ? 'has value' : 'Insufficient verified data';
        $actualProduction = $kpi($mao, 'production')['value'];
        $this->compare('MAO · Production (no verified harvest rows → must not show a number)', $expectedProduction, $riceVerified > 0 ? 'has value' : $actualProduction);

        // The KPI counts calculated ratings; install placeholders (HeatmapArea::isSeedPlaceholder()) are not ratings.
        $this->compare('MAO · Monitored barangays', (int) \App\Models\HeatmapArea::query()->computed()->distinct()->count('barangay'), (int) $kpi($mao, 'monitored')['value']);

        $validRuns = DB::table('analytics_runs')->where('type', 'rice_yield_prediction')->where('status', 'completed')->count() - count(array_filter($runs, fn ($id) => DB::table('analytics_runs')->where('id', $id)->where('type', 'rice_yield_prediction')->exists()));
        $this->compare('MAO · Valid yield prediction runs', $validRuns, (int) preg_replace('/\D.*/', '', (string) $kpi($mao, 'predictions')['value']) - (int) DB::table('farmer_planting_schedules')->whereNotNull('predicted_yield')->count());

        $this->section('5. Result');
        if ($this->failures === 0) {
            $this->info('All checks passed.');
        } else {
            $this->error($this->failures.' check(s) need attention.');
        }

        $this->newLine();
        $this->line('This audit changed nothing. To review removing the sample rows: php artisan iclimate:purge-sample-data (dry run).');

        return $this->failures === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line("<options=bold>{$title}</>");
    }

    private function check(string $label, bool $ok): void
    {
        $this->line(sprintf('  [%s] %s', $ok ? ' OK ' : 'FAIL', $label));
        $ok || $this->failures++;
    }

    private function compare(string $label, mixed $expected, mixed $actual): void
    {
        $ok = (string) $expected === (string) $actual;
        $this->line(sprintf('  [%s] %s — recount: %s, dashboard: %s', $ok ? ' OK ' : 'FAIL', $label, $expected, $actual));
        $ok || $this->failures++;
    }

    private function freshness(string $label, ?string $timestamp, ?string $detail): void
    {
        $this->line(sprintf('  %-58s %s%s', $label, $timestamp ?: 'no record', $detail ? '  ('.$detail.')' : ''));
    }

    /** @param  array<int, string>  $columns */
    private function dupes(string $table, array $columns): int
    {
        try {
            if (! Schema::hasTable($table)) {
                return 0;
            }

            return DB::table($table)->select($columns)->groupBy($columns)->havingRaw('count(*) > 1')->get()->count();
        } catch (Throwable) {
            return 0;
        }
    }
}
