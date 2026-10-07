<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Services\SystemAuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manuscript – Naicel (2026-10-08), Phase 2: the MAO marks an entered climate or production record as validated
 * (checked against its source), or takes the mark back. The station and yield datasets are published sources and are
 * not validated here.
 */
class RecordValidationController extends Controller
{
    /** route segment => model */
    public const TYPES = ['climate-records' => ClimateRecord::class, 'rice-productions' => RiceProduction::class];

    public function validateRecord(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->find($type, $id);
        $record->forceFill(['verified_at' => now(), 'verified_by' => $request->user()->getKey()])->save();
        SystemAuditLogger::forModel('validated', $record, $request);

        return back()->with('success', 'Record marked as validated.');
    }

    public function unvalidate(Request $request, string $type, int $id): RedirectResponse
    {
        $record = $this->find($type, $id);
        $record->forceFill(['verified_at' => null, 'verified_by' => null])->save();
        SystemAuditLogger::forModel('unvalidated', $record, $request);

        return back()->with('success', 'Validation removed.');
    }

    /** Validate every entered record of the type that the MAO has not validated yet (after checking a whole import). */
    public function validateAll(Request $request, string $type): RedirectResponse
    {
        $model = self::TYPES[$type] ?? abort(404);
        $count = $model::query()->whereNull('verified_at')->update(['verified_at' => now(), 'verified_by' => $request->user()->getKey()]);
        SystemAuditLogger::record('Validated All '.class_basename($model).' Records', $request, ['count' => $count]);

        return back()->with('success', $count === 0 ? 'Every record was already validated.' : number_format($count).' records marked as validated.');
    }

    private function find(string $type, int $id): Model
    {
        $model = self::TYPES[$type] ?? abort(404);

        return $model::query()->findOrFail($id);
    }
}
