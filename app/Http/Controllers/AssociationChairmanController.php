<?php

namespace App\Http\Controllers;

use App\Models\AssociationReportingPeriod;
use App\Models\AssociationYieldReport;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssociationChairmanController extends Controller
{
    private function assignment()
    {
        abort_unless(Auth::user()->isAssociationChairman(), 403);

        return Auth::user()->associationChairmanAssignment()->with('association')->firstOrFail();
    }

    public function dashboard()
    {
        $assignment = $this->assignment();
        $periods = AssociationReportingPeriod::query()->latest('due_at')->take(8)->get();
        $reports = AssociationYieldReport::query()->where('farmer_organization_id', $assignment->farmer_organization_id)->with('reportingPeriod')->latest()->get();

        return view('association-chairman.dashboard', compact('assignment', 'periods', 'reports'));
    }

    public function show(int $report)
    {
        $assignment = $this->assignment();
        $report = AssociationYieldReport::query()->where('farmer_organization_id', $assignment->farmer_organization_id)
            ->with(['reportingPeriod', 'reviews.reviewer', 'submissions.submitter'])->findOrFail($report);

        return view('association-chairman.report', compact('assignment', 'report'));
    }

    public function create(AssociationReportingPeriod $period)
    {
        $assignment = $this->assignment();
        $report = AssociationYieldReport::firstOrNew(['farmer_organization_id' => $assignment->farmer_organization_id, 'reporting_period_id' => $period->id]);
        abort_if($report->exists && ! in_array($report->status, [AssociationYieldReport::DRAFT, AssociationYieldReport::NEEDS_REVISION], true), 403);

        return view('association-chairman.report-form', compact('assignment', 'period', 'report'));
    }

    public function store(Request $request, AssociationReportingPeriod $period)
    {
        $assignment = $this->assignment();
        $data = $request->validate([
            'planting_date' => ['nullable', 'date'], 'harvest_date' => ['nullable', 'date', 'after_or_equal:planting_date'],
            'rice_variety' => ['nullable', 'string', 'max:120'], 'irrigation_type' => ['nullable', 'in:Rainfed,Irrigated'],
            'area_planted_hectares' => ['nullable', 'numeric', 'min:0', 'max:100000'], 'area_harvested_hectares' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'production_tons' => ['nullable', 'numeric', 'min:0', 'max:100000000'], 'participating_farmers' => ['nullable', 'integer', 'min:0'],
            'crop_losses' => ['nullable', 'string', 'max:5000'], 'remarks' => ['nullable', 'string', 'max:5000'],
            'supporting_document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'], 'action' => ['required', 'in:draft,submit'],
        ]);
        if (($data['production_tons'] ?? null) !== null && ($data['area_harvested_hectares'] ?? null) === null) {
            return back()->withErrors(['area_harvested_hectares' => 'Harvested area is required when production is reported.'])->withInput();
        }
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $assignment, $period, $data): void {
            // Lock the association as well as an existing report: a first submission has no report row to lock yet.
            $assignment->association()->lockForUpdate()->firstOrFail();
            $report = AssociationYieldReport::query()->where('farmer_organization_id', $assignment->farmer_organization_id)
                ->where('reporting_period_id', $period->id)->lockForUpdate()->firstOrNew();
            abort_if($report->exists && ! in_array($report->status, [AssociationYieldReport::DRAFT, AssociationYieldReport::NEEDS_REVISION], true), 403);
            $report->farmer_organization_id = $assignment->farmer_organization_id;
            $report->reporting_period_id = $period->id;
            unset($data['action'], $data['supporting_document']);
            if ($request->hasFile('supporting_document')) {
                $path = $request->file('supporting_document')->store('association-reports', 'local');
                abort_unless($path, 503, 'Supporting document could not be stored. Please retry.');
                $data['supporting_document_path'] = $path;
            }
            $report->fill($data);
            $report->average_yield_t_per_hectare = $report->production_tons !== null && $report->area_harvested_hectares > 0
                ? round($report->production_tons / $report->area_harvested_hectares, 4) : null;
            $submit = $request->input('action') === 'submit';
            $report->status = $submit ? AssociationYieldReport::SUBMITTED : AssociationYieldReport::DRAFT;
            if ($submit) {
                $report->submitted_by = Auth::id();
                $report->submitted_at = now();
                $report->reviewed_by = null;
                $report->reviewed_at = null;
            }
            $report->save();
            if ($submit) {
                $report->submissions()->create([
                    'submitted_by' => Auth::id(), 'submitted_at' => $report->submitted_at,
                    'version' => ((int) $report->submissions()->max('version')) + 1,
                    'snapshot' => $report->only(['planting_date', 'harvest_date', 'rice_variety', 'irrigation_type', 'area_planted_hectares',
                        'area_harvested_hectares', 'production_tons', 'average_yield_t_per_hectare', 'participating_farmers',
                        'crop_losses', 'remarks', 'supporting_document_path']),
                ]);
                User::query()->where('role', User::ROLE_MAO)->where('status', User::STATUS_ACTIVE)->get()->each(fn (User $mao) => Notification::query()->create([
                    'user_id' => $mao->id, 'title' => 'Association report submitted',
                    'message' => $assignment->association->name.' submitted a rice-yield report for '.$period->label.'.',
                    'type' => 'Announcement', 'category' => 'association_reporting',
                ]));
            }
        });
        $submit = $request->input('action') === 'submit';

        return redirect()->route('association-chairman.dashboard')->with('status', $submit ? 'Report submitted to MAO.' : 'Draft saved.');
    }
}
