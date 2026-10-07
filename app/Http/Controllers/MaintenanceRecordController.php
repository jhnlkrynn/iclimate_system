<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Services\SystemAuditLogger;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Manuscript – Naicel (2026-10-08), Phase 8: Maintenance Records (IT): log updates and fixes; maintenance history. */
class MaintenanceRecordController extends Controller
{
    public function index(Request $request): View
    {
        $category = array_key_exists((string) $request->query('category'), MaintenanceRecord::CATEGORIES) ? $request->query('category') : null;

        return view('admin.maintenance', [
            'records' => MaintenanceRecord::query()->with('performer:id,name')
                ->when($category, fn ($q, $c) => $q->where('category', $c))
                ->latest('performed_at')->latest('id')->paginate(CrudController::PER_PAGE)->withQueryString(),
            'category' => $category,
            'categories' => MaintenanceRecord::CATEGORIES,
            'statuses' => MaintenanceRecord::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $record = MaintenanceRecord::query()->create($this->validated($request) + ['performed_by' => $request->user()->getKey()]);
        SystemAuditLogger::forModel('created', $record, $request);

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance record added.');
    }

    public function update(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $record->update($this->validated($request));
        SystemAuditLogger::forModel('updated', $record, $request);

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance record updated.');
    }

    public function destroy(Request $request, MaintenanceRecord $record): RedirectResponse
    {
        $record->delete();
        SystemAuditLogger::record('Deleted Maintenance Record', $request, ['record_id' => $record->getKey()]);

        return redirect()->route('admin.maintenance.index')->with('success', 'Maintenance record deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::in(array_keys(MaintenanceRecord::CATEGORIES))],
            'status' => ['required', Rule::in(array_keys(MaintenanceRecord::STATUSES))],
            'description' => ['nullable', 'string', 'max:5000'],
            'performed_at' => ['nullable', 'date'],
        ]);
        // Typed in Philippine time; stored in UTC.
        $data['performed_at'] = ! empty($data['performed_at']) ? Carbon::parse($data['performed_at'], BusinessClock::timezone())->utc() : now();

        return $data;
    }
}
