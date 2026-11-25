<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\FarmerProfile;
use App\Models\RiceProduction;
use App\Models\User;
use App\Services\Farmer\FarmerMissingInfoNotifier;
use App\Support\LianBarangays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalizes legacy/invalid barangay values across farmer_profiles, farms, and rice_productions.
 */
class CleanupInvalidBarangays extends Command
{
    protected $signature = 'iclimate:cleanup-barangays {--dry-run : Show before/after counts without modifying the database}';

    protected $description = 'Normalize invalid barangay names (LUYAHN, BARANGAY 1 POB) and null out —/Unknown/blank entries, flagging affected farmers for Complete Profile.';

    private const MAP = [
        'LUYAHN' => 'Luyahan',
        'BARANGAY 1 POB' => 'Barangay 1 (Pob.)',
    ];

    public function handle(FarmerMissingInfoNotifier $missingNotifier): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $valid = LianBarangays::all();
        $tables = ['farmer_profiles', 'farms', 'rice_productions'];

        $rows = [];
        $affectedUserIds = [];

        $run = function () use ($tables, $valid, $dryRun, &$rows, &$affectedUserIds): void {
            foreach ($tables as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'barangay')) {
                    continue;
                }

                $beforeInvalid = DB::table($table)
                    ->where(function ($q) use ($valid): void {
                        $q->whereNull('barangay')->orWhere('barangay', '')->orWhereNotIn('barangay', $valid);
                    })->count();

                $mappedCount = 0;
                $nulledCount = 0;
                $skippedConflicts = 0;

                $records = DB::table($table)
                    ->where(function ($q) use ($valid): void {
                        $q->whereNull('barangay')->orWhere('barangay', '')->orWhereNotIn('barangay', $valid);
                    })
                    ->get(Schema::hasColumn($table, 'user_id') ? ['id', 'barangay', 'user_id'] : ['id', 'barangay']);

                foreach ($records as $rec) {
                    $raw = trim((string) ($rec->barangay ?? ''));
                    $upper = mb_strtoupper($raw);
                    $replacement = self::MAP[$upper] ?? LianBarangays::canonicalName($raw);

                    if ($replacement !== null) {
                        if ($raw === $replacement) {
                            continue;
                        }
                        if ($dryRun) {
                            $mappedCount++;
                            continue;
                        }
                        try {
                            DB::table($table)->where('id', $rec->id)->update(['barangay' => $replacement]);
                            $mappedCount++;
                        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                            $skippedConflicts++;
                        }
                    } else {
                        $nullable = $table !== 'rice_productions';
                        $targetNullVal = $nullable ? null : '';
                        if ($rec->barangay === $targetNullVal) {
                            continue;
                        }
                        if ($dryRun) {
                            $nulledCount++;
                        } else {
                            try {
                                DB::table($table)->where('id', $rec->id)->update(['barangay' => $targetNullVal]);
                                $nulledCount++;
                            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                                $skippedConflicts++;
                                continue;
                            }
                        }
                        if (isset($rec->user_id)) {
                            $affectedUserIds[] = (int) $rec->user_id;
                        }
                    }
                }

                $afterInvalid = $dryRun
                    ? ($beforeInvalid - $mappedCount)
                    : DB::table($table)
                        ->where(function ($q) use ($valid): void {
                            $q->whereNull('barangay')->orWhere('barangay', '')->orWhereNotIn('barangay', $valid);
                        })->count();

                $rows[] = [$table, $beforeInvalid, $mappedCount, $nulledCount, $skippedConflicts, $afterInvalid];
            }

            if (! $dryRun && $affectedUserIds !== []) {
                $affectedUserIds = array_values(array_unique($affectedUserIds));
                User::query()
                    ->whereIn('id', $affectedUserIds)
                    ->whereNotIn('barangay', $valid)
                    ->update(['barangay' => null]);
            }
        };

        if ($dryRun) {
            $run();
        } else {
            DB::transaction($run);
            foreach (array_unique($affectedUserIds) as $uid) {
                $u = User::query()->find($uid);
                if ($u && $u->role === User::ROLE_FARMER) {
                    rescue(fn () => $missingNotifier->sync($u), report: false);
                }
            }
        }

        $this->table(
            ['Table', 'Before (null/invalid)', 'Mapped to canonical', 'Set to NULL/blank', 'Skipped (unique conflict)', 'After (null/invalid)'],
            $rows
        );

        if ($dryRun) {
            $this->warn('Dry run: no database rows were modified. Re-run without --dry-run to apply.');
        } else {
            $this->info('Cleanup applied. Flagged '.count(array_unique($affectedUserIds)).' farmer account(s) for Complete Profile where barangay became NULL.');
        }

        return self::SUCCESS;
    }
}
