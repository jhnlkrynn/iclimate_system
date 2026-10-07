<?php

namespace App\Http\Controllers;

use App\Models\ClimateRecord;
use App\Models\RiceProduction;
use App\Services\SystemAuditLogger;
use App\Support\LianBarangays;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Manuscript – Naicel (2026-10-08), Phase 2: import climate records (e.g. a PAGASA monthly dataset) or rice
 * production records from a CSV file. Each row passes the same checks as the Create form; rows that fail, or that are
 * already recorded, are skipped and listed with the reason. Imported rows start as Not validated.
 */
class RecordImportController extends Controller
{
    public const MAX_ROWS = 5000;

    /** The columns of each template, in order, with an example row. */
    private const TEMPLATES = [
        'climate-records' => [
            'title' => 'Climate Records',
            'columns' => ['record_date', 'rainfall', 'temperature', 'humidity', 'wind_speed', 'season', 'source'],
            'example' => ['2025-06-01', '245.6', '28.4', '84', '2.1', 'Wet', 'PAGASA'],
            'help' => 'One row per date (a monthly record uses the first day of the month). Rainfall in mm, temperature in °C, humidity in %. Season may be left blank: it is taken from the month (Wet = May–October).',
        ],
        'rice-productions' => [
            'title' => 'Rice Production',
            'columns' => ['year', 'season', 'barangay', 'irrigation_type', 'production_month', 'area_hectares', 'area_harvested_hectares', 'total_production', 'yield_per_hectare', 'remarks'],
            'example' => ['2025', 'Wet', 'Balibago', 'Irrigated', '2025-10', '120.5', '118', '560.4', '4.75', 'MAO seasonal report'],
            'help' => 'One row per barangay, season and irrigation type. Areas in hectares, production in metric tons, yield in t/ha. production_month is the harvest month (YYYY-MM). Barangay must be one of Lian\'s 19 official names.',
        ],
    ];

    /** Other header names people use for the same column (lower case, spaces as _). */
    private const ALIASES = [
        'date' => 'record_date', 'rainfall_mm' => 'rainfall', 'rain' => 'rainfall', 'temperature_c' => 'temperature', 'temp' => 'temperature',
        'tmean' => 'temperature', 'rh' => 'humidity', 'relative_humidity' => 'humidity', 'wind' => 'wind_speed', 'windspeed' => 'wind_speed',
        'area_planted' => 'area_hectares', 'area_planted_ha' => 'area_hectares', 'area_harvested' => 'area_harvested_hectares',
        'area_harvested_ha' => 'area_harvested_hectares', 'production' => 'total_production', 'production_mt' => 'total_production',
        'yield' => 'yield_per_hectare', 'yield_t_ha' => 'yield_per_hectare', 'irrigation' => 'irrigation_type', 'month' => 'production_month',
    ];

    public function show(string $type): View
    {
        return view('records.import', ['type' => $type, 'template' => $this->template($type), 'maxRows' => self::MAX_ROWS]);
    }

    private function template(string $type): array
    {
        return self::TEMPLATES[$type] ?? abort(404);
    }

    public function download(string $type): StreamedResponse
    {
        $template = $this->template($type);

        return response()->streamDownload(function () use ($template) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $template['columns']);
            fputcsv($out, $template['example']);
            fclose($out);
        }, $type.'-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $template = $this->template($type);
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:4096']]);

        $rows = $this->read($request->file('file')->getRealPath());
        if ($rows === null) {
            return back()->withErrors(['file' => 'The file has no header row. Download the template to see the columns.']);
        }
        $header = $rows['__header'];
        unset($rows['__header']);
        $required = $type === 'climate-records' ? ['record_date', 'source'] : ['year', 'season', 'barangay', 'irrigation_type', 'production_month'];
        $missing = array_diff($required, $header);
        if ($missing !== []) {
            return back()->withErrors(['file' => 'Missing columns: '.implode(', ', $missing).'.']);
        }
        if (count($rows) > self::MAX_ROWS) {
            return back()->withErrors(['file' => 'The file has more than '.number_format(self::MAX_ROWS).' rows. Split it into smaller files.']);
        }

        $controller = $type === 'climate-records' ? app(ClimateRecordController::class) : app(RiceProductionController::class);
        $rules = $controller->importRules();
        $imported = 0;
        $skipped = [];

        DB::transaction(function () use ($rows, $type, $rules, $template, &$imported, &$skipped) {
            foreach ($rows as $line => $row) {
                $data = $this->normalize($type, array_intersect_key($row, array_flip($template['columns'])));
                $validator = Validator::make($data, $rules);
                if ($validator->fails()) {
                    $skipped[] = ['line' => $line, 'reason' => implode(' ', $validator->errors()->all())];

                    continue;
                }
                $valid = $validator->validated();
                if ($this->alreadyRecorded($type, $valid)) {
                    $skipped[] = ['line' => $line, 'reason' => 'Already in the records.'];

                    continue;
                }
                ($type === 'climate-records' ? ClimateRecord::class : RiceProduction::class)::query()->create($valid);
                $imported++;
            }
        });

        SystemAuditLogger::record('Imported '.$template['title'].' CSV', $request, ['imported' => $imported, 'skipped' => count($skipped)]);

        return redirect()->route('records.import', $type)->with('import_result', [
            'imported' => $imported,
            'skipped' => array_slice($skipped, 0, 200),
            'skipped_total' => count($skipped),
            'file' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    /**
     * Rows keyed by their line number in the file (the header is line 1), each column => value. Null when the file has
     * no header. A UTF-8 byte-order mark and blank lines are ignored.
     *
     * @return array<int|string, array<string, string>>|null
     */
    private function read(string $path): ?array
    {
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle, escape: '\\');
        if (! is_array($header) || array_filter($header) === []) {
            fclose($handle);

            return null;
        }
        $header = array_map(function ($name) {
            $key = strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $name)));
            $key = preg_replace('/[^a-z0-9]+/', '_', $key);
            $key = trim((string) $key, '_');

            return self::ALIASES[$key] ?? $key;
        }, $header);

        $rows = ['__header' => $header];
        $line = 1;
        while (($values = fgetcsv($handle, escape: '\\')) !== false) {
            $line++;
            if ($values === [null] || array_filter($values, fn ($v) => trim((string) $v) !== '') === []) {
                continue;
            }
            $rows[$line] = array_combine($header, array_pad(array_slice($values, 0, count($header)), count($header), ''));
            if (count($rows) > self::MAX_ROWS + 1) {
                break;
            }
        }
        fclose($handle);

        return $rows;
    }

    /** Blank cells become null; dates, months, seasons and barangay names are put in the form the rules expect. */
    private function normalize(string $type, array $row): array
    {
        $row = array_map(fn ($v) => ($v = trim((string) $v)) === '' ? null : $v, $row);

        if ($type === 'climate-records') {
            if ($row['record_date'] ?? null) {
                $time = strtotime($row['record_date']);
                $row['record_date'] = $time ? date('Y-m-d', $time) : $row['record_date'];
            }
            if (empty($row['season']) && ! empty($row['record_date']) && strtotime($row['record_date'])) {
                $row['season'] = ClimateRecord::seasonForMonth((int) date('n', strtotime($row['record_date'])));
            }
        } else {
            if (! empty($row['production_month']) && preg_match('/^(\d{4})-(\d{1,2})/', $row['production_month'], $m)) {
                $row['production_month'] = $m[1].'-'.str_pad($m[2], 2, '0', STR_PAD_LEFT);
            }
            if (! empty($row['barangay'])) {
                $row['barangay'] = LianBarangays::canonicalName($row['barangay']) ?? $row['barangay'];
            }
            if (! empty($row['irrigation_type'])) {
                $row['irrigation_type'] = ucfirst(strtolower($row['irrigation_type']));
            }
        }
        if (! empty($row['season'])) {
            $row['season'] = ucfirst(strtolower($row['season']));
        }

        return $row;
    }

    private function alreadyRecorded(string $type, array $data): bool
    {
        if ($type === 'climate-records') {
            return ClimateRecord::query()->whereDate('record_date', $data['record_date'])->where('source', $data['source'])->exists();
        }

        return RiceProduction::query()
            ->where('barangay', $data['barangay'])->where('season', $data['season'])->where('irrigation_type', $data['irrigation_type'])
            ->whereDate('production_month', $data['production_month'].'-01')
            ->exists();
    }
}
