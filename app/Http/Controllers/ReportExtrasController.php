<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\Climate\ClimateRecordHistory;
use App\Services\SystemAuditLogger;
use App\Support\BusinessClock;
use App\Support\SimpleXlsx;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Manuscript – Naicel (2026-10-08), Phase 7: a saved report as Excel (.xlsx) or PDF, and Draft / Published. The
 * report itself is the saved snapshot (Report::payload): the files hold exactly what Reports shows.
 */
class ReportExtrasController extends Controller
{
    /** A PDF lays out at most this many rows; Excel holds them all. */
    public const PDF_ROW_LIMIT = 1500;

    public function xlsx(Request $request): BinaryFileResponse
    {
        $report = $this->report($request);
        [$columns, $rows] = $this->table($report);
        $path = SimpleXlsx::write($report->report_type, array_values($columns), array_map(
            fn ($row) => array_map(fn ($field) => data_get($row, $field), array_keys($columns)),
            $rows,
        ));
        SystemAuditLogger::record('Exported Report Excel', $request, ['report_id' => $report->id]);

        return response()->download($path, Str::slug($report->title).'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    public function pdf(Request $request): Response
    {
        $report = $this->report($request);
        [$columns, $rows] = $this->table($report);
        $total = count($rows);
        SystemAuditLogger::record('Exported Report PDF', $request, ['report_id' => $report->id]);

        return Pdf::loadView('reports.pdf', [
            'office' => self::office(),
            'title' => $report->title,
            'status' => ($report->status ?? Report::STATUS_PUBLISHED) === Report::STATUS_PUBLISHED ? 'Published' : 'Draft',
            'version' => $report->version ?? 1,
            'generatedAt' => $report->created_at?->copy()->timezone(BusinessClock::timezone()),
            'author' => $report->generatedBy?->name,
            'filters' => $report->savedFilters(),
            'summary' => collect($report->summary())->filter(fn ($i) => isset($i['label']) || isset($i['note']))->values()->all(),
            'columns' => $columns,
            'rows' => array_slice($rows, 0, self::PDF_ROW_LIMIT),
            'total' => $total,
            'truncated' => $total > self::PDF_ROW_LIMIT,
        ])->setPaper('a4', count($columns) > 6 ? 'landscape' : 'portrait')->download(Str::slug($report->title).'.pdf');
    }

    public function publish(Request $request, Report $report): RedirectResponse
    {
        abort_unless($report->is_generated, 404);
        $publish = $request->boolean('publish');
        $report->forceFill([
            'status' => $publish ? Report::STATUS_PUBLISHED : Report::STATUS_DRAFT,
            'published_at' => $publish ? now() : null,
        ])->save();
        SystemAuditLogger::forModel($publish ? 'published' : 'unpublished', $report, $request);

        return redirect()->route('reports.show', $report)->with('success', $publish ? 'Report published.' : 'Report moved back to draft.');
    }

    /** The office name printed on reports (System Settings can change it). */
    public static function office(): string
    {
        $fallback = 'Municipal Agriculture Office · '.\App\Support\LianLocation::MUNICIPALITY.', '.\App\Support\LianLocation::PROVINCE;

        return class_exists(\App\Support\SystemSettings::class) ? (string) \App\Support\SystemSettings::get('office_name', $fallback) : $fallback;
    }

    private function report(Request $request): Report
    {
        abort_unless($request->filled('report'), 404);

        return Report::query()->realGenerated()->with('generatedBy')->findOrFail($request->integer('report'));
    }

    /** @return array{0: array<string, string>, 1: array<int, mixed>} the same columns and rows as the CSV export */
    private function table(Report $report): array
    {
        $columns = $report->savedColumns();
        if ($report->typeKey() === 'climate_records') {
            unset($columns['wind_speed']);
        }
        $rows = array_map(function ($row) use ($report) {
            if ($report->typeKey() === 'climate_records' && is_array($row) && array_key_exists('source', $row)) {
                $row['source'] = ClimateRecordHistory::displaySource($row['source']);
            }

            return $row;
        }, $report->savedRecords());

        return [$columns, $rows];
    }
}
