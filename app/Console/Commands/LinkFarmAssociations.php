<?php

namespace App\Console\Commands;

use App\Services\Farms\FarmAssociations;
use Illuminate\Console\Command;

/**
 * Links farms that have no association answer yet to the farmer's primary organization when it is in the farm's
 * barangay (the same backfill the 2026_10_30 migration runs). Safe to run again: answered farms are never changed.
 */
class LinkFarmAssociations extends Command
{
    protected $signature = 'farms:link-associations';

    protected $description = 'Link unanswered farms to the farmer\'s primary association when it is in the farm\'s barangay';

    public function handle(): int
    {
        if (! FarmAssociations::available()) {
            $this->error('The farms association columns are missing. Run the migrations first.');

            return self::FAILURE;
        }

        $counts = FarmAssociations::backfill();
        $this->table(['Result', 'Farms'], [
            ['Linked to the primary organization', $counts['linked']],
            ['Not declared', $counts['not_declared']],
            ['  no primary organization', $counts['no_organization']],
            ['  primary organization in another barangay', $counts['other_barangay']],
        ]);

        return self::SUCCESS;
    }
}
