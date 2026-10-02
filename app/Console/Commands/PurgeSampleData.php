<?php

namespace App\Console\Commands;

use App\Support\DashboardData;
use App\Support\SampleDataDetector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

/**
 * Removes the seeded sample rows the dashboards already leave out.
 *
 * Dry run by default: it only lists what it WOULD delete. Deleting needs
 * --force, asks for confirmation, and takes a database backup first (the run
 * is aborted if the backup fails). Only rows recognised by SampleDataDetector
 * are touched; real records are never selected.
 */
class PurgeSampleData extends Command
{
    protected $signature = 'iclimate:purge-sample-data
        {--force : Actually delete (default is a dry run that only lists the rows)}
        {--skip-backup : Do not take a backup first (not recommended)}';

    protected $description = 'List (dry run) or delete the seeded sample rows and invalid prediction runs found by the data audit.';

    /** table => label */
    private const TABLES = [
        'rice_productions' => 'rice production rows created by the database seeder',
        'climate_records' => 'climate rows created by the seeder, plus impossible readings',
        'notifications' => 'placeholder notifications created by the seeder',
        'planting_advisories' => 'placeholder advisories created by the seeder',
        'analytics_runs' => 'prediction runs made by the under-trained weather model',
    ];

    public function handle(SampleDataDetector $detector): int
    {
        // The detector honours the dashboard switch; purge reasons about the data itself.
        config(['iclimate.exclude_sample_data' => true]);
        Cache::flush();

        $plan = [];
        foreach (self::TABLES as $table => $label) {
            $ids = $detector->excludedIds($table);
            $plan[$table] = $ids;
            $this->line(sprintf('  %-22s %d row(s)  — %s', $table, count($ids), $label));
            if ($ids !== []) {
                $this->line('      ids: '.implode(', ', array_slice($ids, 0, 40)).(count($ids) > 40 ? ' …' : ''));
            }
        }

        $total = array_sum(array_map('count', $plan));

        if ($total === 0) {
            $this->info('Nothing to remove.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn("Dry run: {$total} row(s) would be deleted. Nothing was changed.");
            $this->line('Review the list, then run again with --force to delete (a backup is taken first).');

            return self::SUCCESS;
        }

        if (! $this->confirm("Delete {$total} row(s) from the live database?", false)) {
            $this->line('Cancelled. Nothing was changed.');

            return self::SUCCESS;
        }

        if (! $this->option('skip-backup')) {
            $this->line('Taking a database backup first…');
            if (Artisan::call('iclimate:backup-database') !== self::SUCCESS) {
                $this->error('Backup failed — nothing was deleted.');
                $this->line(trim(Artisan::output()));

                return self::FAILURE;
            }
            $this->line(trim(Artisan::output()));
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan as $table => $ids) {
                foreach (array_chunk($ids, 500) as $chunk) {
                    DB::table($table)->whereIn('id', $chunk)->delete();
                }
            }
        });

        DashboardData::bump();
        Cache::flush();

        $this->info("Deleted {$total} row(s). Run `php artisan iclimate:data-audit` to confirm.");

        return self::SUCCESS;
    }
}
