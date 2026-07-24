<?php

namespace App\Console\Commands;

use App\Services\Advisories\PagasaAdvisoryService;
use Illuminate\Console\Command;

class FetchPagasaAdvisories extends Command
{
    protected $signature = 'iclimate:fetch-pagasa-advisories {--force : Ignore the short cache and fetch PAGASA now}';

    protected $description = 'Fetch official PAGASA online advisories and store Lian, Batangas matches.';

    public function handle(PagasaAdvisoryService $service): int
    {
        $summary = $service->fetchAndStore((bool) $this->option('force'));

        $this->info($summary['message']);
        $this->line('Sources checked: '.$summary['sources_checked']);
        $this->line('Created: '.$summary['advisories_created']);
        $this->line('Duplicates skipped: '.$summary['advisories_skipped_as_duplicates']);
        $this->line('No Lian/Batangas match: '.$summary['sources_without_lian_batangas_match']);

        foreach ($summary['errors'] as $error) {
            $this->warn($error);
        }

        return empty($summary['errors']) ? self::SUCCESS : self::FAILURE;
    }
}
