<?php

namespace App\Console\Commands;

use Database\Seeders\FarmerDemoSeeder;
use Illuminate\Console\Command;

class DemoFarmers extends Command
{
    protected $signature = 'demo:farmers {--fresh-activity : Remove and regenerate demo farming activity first} {--allow-remote : Explicitly allow seeding a configured remote database such as Supabase}';

    protected $description = 'Create the supplied demo farmer accounts and realistic local farming activity.';

    public function handle(FarmerDemoSeeder $seeder): int
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run demo seeding while APP_ENV=production.');

            return self::FAILURE;
        }

        try {
            $report = $seeder->run(
                freshActivity: (bool) $this->option('fresh-activity'),
                allowRemote: (bool) $this->option('allow-remote'),
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Demo farmers complete: {$report['accounts_created']} created, {$report['accounts_updated']} updated, {$report['schedules_created']} schedules.");
        $this->line('Credentials: storage/app/demo/farmer-credentials.csv');
        $this->line('Report: storage/app/demo/seed-report.md');

        return self::SUCCESS;
    }
}
