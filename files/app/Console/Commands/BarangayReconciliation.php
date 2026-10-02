<?php

namespace App\Console\Commands;

use App\Models\FarmerProfile;
use App\Support\LianBarangays;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only: writes a CSV of farmer profiles whose barangay is not one of Lian's 19
 * barangays, with a SUGGESTED match for a person to verify.
 *
 * Nothing is saved. A suggestion is never applied automatically: a human fills the
 * "confirmed_barangay" column, then `iclimate:apply-barangay-corrections` applies
 * only those confirmed rows (dry run unless --force).
 */
class BarangayReconciliation extends Command
{
    protected $signature = 'iclimate:barangay-reconciliation {--path=barangay-reconciliation.csv : file name under storage/app/reports}';

    protected $description = 'Export farmer profiles with an unsupported barangay value, with suggested matches for manual verification (changes nothing).';

    public const HEADER = [
        'profile_id', 'user_id', 'current_barangay', 'suggested_match', 'confidence', 'reason',
        'organization', 'organization_barangay', 'farm_type', 'address_mentions_barangay', 'confirmed_barangay',
    ];

    public function handle(): int
    {
        $rows = [];

        FarmerProfile::query()->with('farmerOrganization')->orderBy('id')->get()
            ->filter(fn (FarmerProfile $p) => LianBarangays::canonicalName($p->barangay) === null)
            ->each(function (FarmerProfile $p) use (&$rows): void {
                [$suggestion, $confidence, $reason] = $this->suggest((string) $p->barangay);
                $org = $p->farmerOrganization;
                $orgBarangay = LianBarangays::canonicalName($org?->barangay);

                $rows[] = [
                    $p->id, $p->user_id, $p->barangay, $suggestion, $confidence, $reason,
                    $org?->name, $org?->barangay, $p->farm_type, $this->addressMention((string) $p->address),
                    '', // confirmed_barangay: to be filled in by a person
                ];
                if ($orgBarangay && $orgBarangay !== $suggestion) {
                    $rows[array_key_last($rows)][5] .= " Organization is registered in {$orgBarangay} (context only; a farmer may farm elsewhere).";
                }
            });

        $relative = 'reports/'.ltrim((string) $this->option('path'), '/');
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, self::HEADER);
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        Storage::disk('local')->put($relative, stream_get_contents($stream));
        fclose($stream);

        $this->table(['profile', 'current', 'suggested', 'confidence'], array_map(fn ($r) => [$r[0], $r[2], $r[3] ?: '—', $r[4]], $rows));
        $this->info(count($rows).' profile(s) need manual verification. File: storage/app/private/'.$relative.' (or storage/app/'.$relative.' depending on disk config).');
        $this->line('No data was changed. Fill "confirmed_barangay" after verifying, then run iclimate:apply-barangay-corrections.');

        $users = DB::table('users')->whereNotNull('barangay')->get(['id', 'barangay'])
            ->filter(fn ($u) => LianBarangays::canonicalName($u->barangay) === null)->count();
        if ($users > 0) {
            $this->line("Note: {$users} user account(s) also carry an unsupported barangay value (users.barangay); the apply command updates these together with the matching profile.");
        }

        return self::SUCCESS;
    }

    /** @return array{0: ?string, 1: string, 2: string} [suggestion, confidence, reason] */
    private function suggest(string $value): array
    {
        $needle = $this->squash($value);
        $canonical = LianBarangays::all();

        if ($needle === '' || $needle === 'unknown') {
            return [null, 'none', 'No usable text; cannot be inferred.'];
        }

        if (str_contains($needle, 'poblacion')) {
            return [null, 'none', 'Poblacion covers Barangays 1-5 (Pob.); the specific one cannot be inferred.'];
        }

        // "Barangay 1 Pob" style: same number as a canonical poblacion barangay.
        if (preg_match('/(?:barangay|brgy)?\s*([1-5])\s*(?:pob)/i', $value, $m)) {
            $name = "Barangay {$m[1]} (Pob.)";

            return [$name, 'high', "Format variant: number {$m[1]} matches {$name}."];
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $tie = false;
        foreach ($canonical as $name) {
            $distance = levenshtein($needle, $this->squash($name));
            if ($distance < $bestDistance) {
                [$best, $bestDistance, $tie] = [$name, $distance, false];
            } elseif ($distance === $bestDistance) {
                $tie = true;
            }
        }

        if ($best !== null && ! $tie && $bestDistance <= 2) {
            return [$best, $bestDistance === 1 ? 'medium' : 'low', "Likely typo of {$best} (edit distance {$bestDistance}). Verify before accepting."];
        }

        return [null, 'none', 'No close match among the 19 barangays.'];
    }

    private function squash(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($value)) ?: '';
    }

    /** Only reports WHICH barangay name the address text contains, never the address itself. */
    private function addressMention(string $address): string
    {
        $haystack = $this->squash($address);
        $found = [];
        if ($haystack !== '') {
            foreach (LianBarangays::all() as $name) {
                $token = $this->squash(preg_replace('/\s*\(pob\.\)/i', '', $name) ?: $name);
                if ($token !== '' && ! ctype_digit(substr($token, -1)) && str_contains($haystack, $token)) {
                    $found[] = $name;
                }
            }
        }

        return implode('; ', $found);
    }
}
