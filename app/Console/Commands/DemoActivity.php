<?php

namespace App\Console\Commands;

use Database\Seeders\FarmerActivityDemoSeeder;
use Illuminate\Console\Command;

class DemoActivity extends Command
{
    protected $signature = 'demo:activity {--reset : Remove tracked demo activity before regenerating it} {--allow-remote : Explicitly allow seeding a configured remote database such as Supabase}';

    protected $description = 'Populate complete activity for seeded demo farmers only.';

    public function handle(FarmerActivityDemoSeeder $seeder): int
    {
        try {
            $report = $seeder->run((bool) $this->option('reset'), (bool) $this->option('allow-remote'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
        $this->info('Demo activity complete: '.$report['schedules_created'].' schedules created, '.$report['schedules_updated'].' updated, '.$report['farms_created'].' farms created, '.$report['productions_created'].' aggregate rows created.');
        $this->table(['Season', 'Official ha', 'Seeded ha', 'Official t', 'Seeded t', 'Farmers', 'Harvested', 'Growing', 'Planned'], collect($report['season_report'] ?? [])->map(function (array $row, string $label): array {
            return [$label, $row['official_area'] ?? 'projection', $row['seeded_area'] ?? '—', $row['official_production'] ?? 'projection', $row['seeded_production'] ?? '—', $row['farmers_planting'] ?? '—', $row['harvested'] ?? '—', $row['growing'] ?? '—', $row['planned'] ?? '—'];
        })->values()->all());
        $this->line('Seeded farmers: '.($report['seeded_farmers'] ?? 0).'; with a farm: '.($report['farmers_with_farm'] ?? 0).'; with activity: '.($report['farmers_with_schedule'] ?? 0).'.');
        if (! empty($report['conflicts'])) $this->warn('Existing real rice-production rows with differing values were preserved; see storage/app/demo/activity-report.json.');
        $this->line('IDs: storage/app/demo/seeded-activity-ids.json');
        return self::SUCCESS;
    }
}
