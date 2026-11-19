<?php

namespace App\Http\Controllers;

use App\Models\AssociationYieldReport;
use App\Support\MaoScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AssociationReportDocumentController extends Controller
{
    public function __invoke(Request $request, AssociationYieldReport $report, ?int $submission = null)
    {
        $user = $request->user();
        if ($user->isMao()) {
            abort_unless(MaoScope::canManageBarangay($report->association->barangay, $user), 403);
        } else {
            abort_unless($user->isAssociationChairman()
                && $user->associationChairmanAssignment()->where('farmer_organization_id', $report->farmer_organization_id)->exists(), 403);
        }
        $path = $submission === null ? $report->supporting_document_path
            : ($report->submissions()->findOrFail($submission)->snapshot['supporting_document_path'] ?? null);
        abort_unless(is_string($path) && preg_match('/\Aassociation-reports\/[a-zA-Z0-9_-]+\.(pdf|jpe?g|png)\z/', $path), 404);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path);
    }
}
