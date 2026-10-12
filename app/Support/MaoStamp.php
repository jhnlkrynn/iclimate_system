<?php

namespace App\Support;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Panel revision 4.4 C: "Verified by: Evelyn M. Mayari on Oct 10, 2026, 4:16 PM" lines for a record, from the columns
 * it has: created_by / created_at, updated_by / updated_at, verified_by / verified_at, approved_by / approved_at,
 * reviewed_by / reviewed_at.
 */
final class MaoStamp
{
    private const STAMPS = [
        ['Created by', 'created_by', 'created_at'],
        ['Edited by', 'updated_by', 'updated_at'],
        ['Verified by', 'verified_by', 'verified_at'],
        ['Approved by', 'approved_by', 'approved_at'],
        ['Reviewed by', 'reviewed_by', 'reviewed_at'],
    ];

    /**
     * @return array<int, array{label: string, name: string, position: ?string, at: ?string, text: string}>
     */
    public static function lines(mixed $record): array
    {
        if (! $record instanceof Model) {
            return [];
        }
        $ids = [];
        foreach (self::STAMPS as [, $byColumn]) {
            if ($record->getAttribute($byColumn)) {
                $ids[] = (int) $record->getAttribute($byColumn);
            }
        }
        if ($ids === []) {
            return [];
        }
        $users = User::query()->whereIn('id', array_unique($ids))->get(['id', 'name', 'position', 'role'])->keyBy('id');

        $lines = [];
        foreach (self::STAMPS as [$label, $byColumn, $atColumn]) {
            $id = $record->getAttribute($byColumn);
            if (! $id || ! ($user = $users->get((int) $id))) {
                continue;
            }
            // "Edited by" adds nothing when the creator saved it once and nobody edited it since.
            if ($byColumn === 'updated_by' && (int) $id === (int) $record->getAttribute('created_by')
                && self::sameMoment($record->getAttribute('created_at'), $record->getAttribute('updated_at'))) {
                continue;
            }
            $at = self::format($record->getAttribute($atColumn));
            $lines[] = [
                'label' => $label,
                'name' => $user->name,
                'position' => $user->position,
                'at' => $at,
                'text' => $label.': '.$user->name.($at ? ' on '.$at : ''),
            ];
        }

        return $lines;
    }

    /** "Oct 10, 2026, 4:16 PM" in Manila time. */
    public static function format(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        $date = $value instanceof CarbonInterface ? $value : rescue(fn () => Carbon::parse($value), null, false);

        return $date?->copy()->timezone(LianLocation::TIMEZONE)->format('M j, Y, g:i A');
    }

    private static function sameMoment(mixed $a, mixed $b): bool
    {
        return $a && $b && abs(Carbon::parse($a)->diffInSeconds(Carbon::parse($b), false)) < 5;
    }
}
