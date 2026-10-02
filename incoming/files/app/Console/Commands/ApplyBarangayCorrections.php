<?php

namespace App\Console\Commands;

use App\Models\FarmerProfile;
use App\Support\LianBarangays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Controlled correction of farmer barangays. Reads the reconciliation CSV and
 * applies ONLY rows whose "confirmed_barangay" a person filled in and that is one of
 * the 19 real barangays. Suggested matches are never applied. Dry run unless --force.
 */
class ApplyBarangayCorrections extends Command
{
    protected $signature = 'iclimate:apply-barangay-corrections {file=barangay-reconciliation.csv : CSV under storage/app/reports} {--force : actually save}';

    protected $description = 'Apply human-confirmed barangay corrections from the reconciliation CSV (dry run unless --force).';

    public function handle(): int
    {
        $path = 'reports/'.ltrim((string) $this->argument('file'), '/');
        if (! Storage::disk('local')->exists($path)) {
            $this->error("File not found: storage/app/{$path}");

            return self::FAILURE;
        }

        $lines = array_map('str_getcsv', preg_split('/\r\n|\n|\r/', trim(Storage::disk('local')->get($path))) ?: []);
        $header = array_shift($lines);
        $applied = 0;

        foreach ($lines as $line) {
            $row = array_combine($header, array_pad($line, count($header), ''));
            $confirmed = trim((string) ($row['confirmed_barangay'] ?? ''));
            if ($confirmed === '') {
                continue; // not verified yet — never auto-apply the suggestion
            }
            if (! in_array($confirmed, LianBarangays::all(), true)) {
                $this->warn("Profile {$row['profile_id']}: '{$confirmed}' is not one of the 19 supported barangays; skipped.");

                continue;
            }
            $profile = FarmerProfile::query()->find($row['profile_id']);
            if (! $profile || $profile->barangay !== $row['current_barangay']) {
                $this->warn("Profile {$row['profile_id']}: current value no longer matches the file; skipped.");

                continue;
            }

            $this->line("Profile {$profile->id}: '{$profile->barangay}' -> '{$confirmed}'");
            if ($this->option('force')) {
                DB::transaction(function () use ($profile, $confirmed): void {
                    $old = $profile->barangay;
                    $profile->forceFill(['barangay' => $confirmed])->save();
                    DB::table('users')->where('id', $profile->user_id)->where('barangay', $old)->update(['barangay' => $confirmed]);
                });
            }
            $applied++;
        }

        $this->info($this->option('force') ? "Corrected {$applied} profile(s)." : "Dry run: {$applied} profile(s) would be corrected. Re-run with --force to save.");

        return self::SUCCESS;
    }
}
