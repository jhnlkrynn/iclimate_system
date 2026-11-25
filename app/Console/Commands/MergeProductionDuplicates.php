<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MergeProductionDuplicates extends Command
{
    protected $signature = 'iclimate:merge-production-duplicates
        {--dry-run : Preview which duplicate rows would be kept and deleted without changing the database}
        {--delete : Actually merge missing fields and delete the duplicate rows}';

    protected $description = 'Find and merge duplicate rice_productions rows whose blank/placeholder barangays collide on (barangay, season, year, irrigation_type).';

    private const PLACEHOLDER_BARANGAYS = ['', '—', '-', '--', 'unknown', 'n/a', 'na', 'none', 'null'];

    public function handle(): int
    {
        if (! Schema::hasTable('rice_productions')) {
            $this->warn('rice_productions table not found.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run') || ! (bool) $this->option('delete');
        $rows = DB::table('rice_productions')->orderBy('year')->orderBy('season')->orderBy('id')->get();

        $groups = $rows->groupBy(function (object $row): string {
            $raw = trim((string) ($row->barangay ?? ''));
            $norm = in_array(mb_strtolower($raw), self::PLACEHOLDER_BARANGAYS, true) ? '' : $raw;

            return implode('|', [$norm, (string) $row->season, (string) $row->year, (string) $row->irrigation_type]);
        })->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate rice_productions groups found.');

            return self::SUCCESS;
        }

        $tableRows = [];
        $mergedCount = 0;
        $deletedCount = 0;

        foreach ($groups as $key => $group) {
            $sorted = $group->sort(function (object $a, object $b): int {
                $scoreDiff = $this->completenessScore($b) <=> $this->completenessScore($a);
                if ($scoreDiff !== 0) {
                    return $scoreDiff;
                }

                $timeDiff = strcmp((string) ($b->updated_at ?? ''), (string) ($a->updated_at ?? ''));
                if ($timeDiff !== 0) {
                    return $timeDiff;
                }

                return (int) $b->id <=> (int) $a->id;
            })->values();

            $keep = $sorted->first();
            $dups = $sorted->slice(1)->values();

            $keepUpdates = [];
            $rawKeepBarangay = trim((string) ($keep->barangay ?? ''));
            if (in_array(mb_strtolower($rawKeepBarangay), self::PLACEHOLDER_BARANGAYS, true) && $keep->barangay !== '') {
                $keepUpdates['barangay'] = '';
            }

            foreach ($dups as $dup) {
                foreach (['area_hectares', 'area_harvested_hectares', 'total_production', 'yield_per_hectare', 'notes', 'source'] as $col) {
                    if (property_exists($keep, $col) && ($keep->{$col} === null || $keep->{$col} === '') && ($dup->{$col} ?? null) !== null && $dup->{$col} !== '') {
                        $keepUpdates[$col] = $dup->{$col};
                        $keep->{$col} = $dup->{$col};
                    }
                }
            }

            if (! $dryRun) {
                DB::transaction(function () use ($keep, $dups, $keepUpdates): void {
                    $dupIds = $dups->pluck('id')->all();
                    DB::table('rice_productions')->whereIn('id', $dupIds)->delete();
                    if ($keepUpdates !== []) {
                        DB::table('rice_productions')->where('id', $keep->id)->update($keepUpdates);
                    }
                });
                $mergedCount++;
                $deletedCount += $dups->count();
            }

            $tableRows[] = [
                $keep->id.' ('.json_encode($keep->barangay).')',
                $dups->map(fn ($d) => $d->id.' ('.json_encode($d->barangay).')')->implode(', '),
                "{$keep->season} {$keep->year} · {$keep->irrigation_type}",
                $dryRun ? 'Would keep #'.$keep->id.' & delete '.$dups->count() : 'Kept #'.$keep->id.', deleted '.$dups->count(),
            ];
        }

        $this->table(['Keep', 'Duplicates', 'Key', 'Action'], $tableRows);

        if ($dryRun) {
            $this->warn('Dry run only — no rows were modified or deleted. Re-run with --delete to apply.');
        } else {
            $this->info("Merged {$mergedCount} duplicate group(s) and deleted {$deletedCount} duplicate row(s).");
        }

        return self::SUCCESS;
    }

    private function completenessScore(object $row): int
    {
        $score = 0;
        foreach (get_object_vars($row) as $key => $value) {
            if (in_array($key, ['id', 'created_at', 'updated_at', 'barangay'], true)) {
                continue;
            }
            if ($value !== null && $value !== '') {
                $score++;
            }
        }
        if (trim((string) ($row->barangay ?? '')) === '') {
            $score++;
        }

        return $score;
    }
}
