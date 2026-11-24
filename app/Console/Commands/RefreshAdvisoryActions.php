<?php

namespace App\Console\Commands;

use App\Models\PlantingAdvisory;
use App\Services\Advisories\AdvisoryActionRules;
use Illuminate\Console\Command;

class RefreshAdvisoryActions extends Command
{
    protected $signature = 'iclimate:refresh-advisory-actions {--all : Update all advisories, not only active ones}';

    protected $description = 'Re-run AdvisoryActionRules on existing advisories so recommended_action and localized actions stay current.';

    public function handle(AdvisoryActionRules $rules): int
    {
        $query = PlantingAdvisory::query();
        if (! $this->option('all')) {
            $query->active();
        }

        $updated = 0;
        $query->chunkById(100, function ($advisories) use ($rules, &$updated): void {
            foreach ($advisories as $advisory) {
                $text = implode(' ', array_filter([
                    (string) $advisory->title,
                    (string) $advisory->summary,
                    (string) ($advisory->message ?: $advisory->content),
                ]));
                $actions = $rules->metadataFor($text, $advisory->severity);
                $recommended = $rules->toActionString($actions['actions_en']);
                $metadata = array_merge($advisory->metadata ?? [], $actions);

                $advisory->update([
                    'recommended_action' => $recommended,
                    'summary' => AdvisoryActionRules::toSentenceCase((string) $advisory->summary),
                    'metadata' => $metadata,
                ]);
                $updated++;
            }
        });

        $this->info("Updated actions on {$updated} advisories.");

        return self::SUCCESS;
    }
}
