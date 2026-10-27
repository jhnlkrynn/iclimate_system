<?php

namespace App\Support\RiceVarieties;

use App\Support\SchemaCache;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The client's NSIC list as a PDF ("View source list" on the Rice Varieties page). It is only shown, never read as data:
 * rice-varieties:import loads the .xlsx of the same list. The copy kept with the code (database/data) is published to
 * the public disk (storage/app/public, linked at /storage) each time the import runs.
 */
final class SourceDocument
{
    public const PUBLIC_PATH = 'references/nsic-registered_rice.pdf';

    public static function source(): string
    {
        return (string) config('rice_varieties.source_pdf', database_path('data/nsic-registered_rice.pdf'));
    }

    /** Copies the PDF to the public disk when it is missing or has changed. True when the public copy is there. */
    public static function publish(): bool
    {
        $disk = Storage::disk('public');
        if (! is_file(self::source())) {
            return $disk->exists(self::PUBLIC_PATH);
        }
        if (! $disk->exists(self::PUBLIC_PATH) || md5_file(self::source()) !== md5((string) $disk->get(self::PUBLIC_PATH))) {
            $disk->put(self::PUBLIC_PATH, (string) file_get_contents(self::source()));
        }

        return true;
    }

    /** The link to the published PDF, or null when it has not been published on this server. */
    public static function url(): ?string
    {
        return Storage::disk('public')->exists(self::PUBLIC_PATH) ? Storage::disk('public')->url(self::PUBLIC_PATH) : null;
    }

    /** @return array{at: CarbonInterface, rows: int}|null the latest import run */
    public static function lastImport(): ?array
    {
        if (! SchemaCache::hasTable('rice_variety_imports')) {
            return null;
        }
        $run = DB::table('rice_variety_imports')->orderByDesc('imported_at')->orderByDesc('id')->first(['imported_at', 'rows']);

        return $run ? ['at' => Carbon::parse($run->imported_at), 'rows' => (int) $run->rows] : null;
    }
}
