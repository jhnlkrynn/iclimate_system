<?php

namespace App\Support;

use App\Models\Farm;
use App\Models\RiceVariety;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * The figures on the landing page's numbers band, counted from the database and cached until midnight (Manila time),
 * so the public page never runs these counts on every visit. Null when the database cannot be read: the band is hidden.
 */
final class LandingStats
{
    public const CACHE_KEY = 'landing:stats';

    /** @return array{farmers: int, farms: int, barangays: int, varieties: int}|null */
    public static function get(): ?array
    {
        try {
            return Cache::remember(self::CACHE_KEY, BusinessClock::now()->endOfDay(), fn (): array => self::count());
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{farmers: int, farms: int, barangays: int, varieties: int} */
    public static function count(): array
    {
        $barangays = Farm::query()->active()->whereNotNull('barangay')->distinct()->pluck('barangay')
            ->map(fn (string $name): ?string => LianBarangays::canonicalName($name))
            ->filter()->unique();

        return [
            // Approved farmer accounts: pending, rejected, inactive and deleted accounts are not counted.
            'farmers' => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->count(),
            'farms' => Farm::query()->active()->count(),
            'barangays' => $barangays->count(),
            'varieties' => RiceVariety::query()->active()->count(),
        ];
    }
}
