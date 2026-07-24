<?php

namespace App\Console\Commands;

use App\Services\Advisories\AdvisoryGenerationService;
use Illuminate\Console\Command;

class ExpireAgriculturalAdvisories extends Command
{
    protected $signature = 'iclimate:expire-advisories';

    protected $description = 'Mark agricultural advisories as expired after their validity period.';

    public function handle(AdvisoryGenerationService $service): int
    {
        $expired = $service->expireOutdated();

        $this->info("Expired advisories updated: {$expired}");

        return self::SUCCESS;
    }
}
