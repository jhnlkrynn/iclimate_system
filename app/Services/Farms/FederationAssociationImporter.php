<?php

namespace App\Services\Farms;

use App\Models\FarmerOrganization;
use App\Services\Risk\HeatMapLayerService;
use App\Support\CacheScope;
use App\Support\LianBarangays;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use RuntimeException;

final class FederationAssociationImporter
{
    public const DEFAULT_PATH = 'database/data/lian-farmer-associations.xlsx';

    public const INFERRED_SOURCE = 'inferred from federation totals, pending MAO confirmation';

    /** @var list<string> */
    public const RAINFED_ASSOCIATIONS = [
        'Luyahan Farmers Association',
        'Matabungkay Farmers Association',
        'Tan-ag Farmers Association',
    ];

    /** @return array{rows: int, created: int, updated: int, unchanged: int, irrigated_ha: float, rainfed_ha: float, total_ha: float} */
    public function import(?string $path = null): array
    {
        $resolved = $this->resolvePath($path);
        if (! is_file($resolved)) {
            throw new RuntimeException("Federation spreadsheet not found at {$resolved}.");
        }

        $spreadsheet = IOFactory::load($resolved);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $highestCol = $sheet->getHighestDataColumn();
        $rows = $sheet->rangeToArray("A1:{$highestCol}{$highestRow}", null, true, false, false);

        $sheetIrrigated = null;
        $sheetRainfed = null;
        $headerIndex = null;

        foreach ($rows as $index => $row) {
            foreach ($row as $colIndex => $cell) {
                $text = strtoupper(trim((string) $cell));
                if ($text === 'IRRIGATED (HA)' && isset($row[$colIndex + 1]) && is_numeric($row[$colIndex + 1])) {
                    $sheetIrrigated = round((float) $row[$colIndex + 1], 2);
                } elseif ($text === 'NON IRRIGATED (HA)' && isset($row[$colIndex + 1]) && is_numeric($row[$colIndex + 1])) {
                    $sheetRainfed = round((float) $row[$colIndex + 1], 2);
                }
            }

            if ($headerIndex === null && strtoupper(trim((string) ($row[0] ?? ''))) === 'BARANGAY') {
                $headerIndex = $index;
            }
        }

        if ($headerIndex === null) {
            throw new RuntimeException('Could not find the BARANGAY header row in the federation spreadsheet.');
        }

        if ($sheetIrrigated === null || $sheetRainfed === null) {
            throw new RuntimeException('Could not find IRRIGATED (HA) and NON IRRIGATED (HA) totals in the federation spreadsheet.');
        }

        $parsed = [];
        $currentBarangay = null;

        for ($i = $headerIndex + 1; $i < count($rows); $i++) {
            $row = $rows[$i];
            $rawBarangay = trim((string) ($row[0] ?? ''));
            if ($rawBarangay !== '') {
                $currentBarangay = LianBarangays::canonicalName($rawBarangay) ?? $rawBarangay;
            }

            $name = trim((string) ($row[1] ?? ''));
            if ($name === '') {
                continue;
            }

            if ($currentBarangay === null || $currentBarangay === '') {
                throw new RuntimeException("Missing barangay for association '{$name}' on row ".($i + 1).'.');
            }

            $registrationNo = trim((string) ($row[2] ?? '')) ?: null;
            $dateRegistered = $this->parseDate($row[3] ?? null);
            // Columns 4 (PRESIDENT) and 5 (CELLPHONE) are intentionally ignored and never imported.
            $area = is_numeric($row[6] ?? null) ? round((float) $row[6], 2) : null;
            $members = is_numeric($row[7] ?? null) ? (int) round((float) $row[7]) : null;

            $isRainfed = collect(self::RAINFED_ASSOCIATIONS)
                ->contains(fn (string $target): bool => strcasecmp($target, $name) === 0);

            $parsed[] = [
                'barangay' => $currentBarangay,
                'name' => $name,
                'registration_no' => $registrationNo,
                'date_registered' => $dateRegistered,
                'area_hectares' => $area,
                'farmer_count' => $members,
                'farm_type' => $isRainfed ? 'Rainfed' : 'Irrigated',
            ];
        }

        if ($parsed === []) {
            throw new RuntimeException('No association rows were found in the federation spreadsheet.');
        }

        $computedIrrigated = round((float) collect($parsed)->where('farm_type', 'Irrigated')->sum('area_hectares'), 2);
        $computedRainfed = round((float) collect($parsed)->where('farm_type', 'Rainfed')->sum('area_hectares'), 2);

        if (abs($computedIrrigated - $sheetIrrigated) > 0.01) {
            throw new RuntimeException("Irrigated area total mismatch: computed {$computedIrrigated} ha vs sheet {$sheetIrrigated} ha.");
        }

        if (abs($computedRainfed - $sheetRainfed) > 0.01) {
            throw new RuntimeException("Non-irrigated area total mismatch: computed {$computedRainfed} ha vs sheet {$sheetRainfed} ha.");
        }

        $hasFederationColumns = Schema::hasColumns('farmer_organizations', ['registration_no', 'date_registered', 'farm_type_source', 'farm_type_confirmed_at']);
        $created = 0;
        $updated = 0;
        $unchanged = 0;

        DB::transaction(function () use ($parsed, $hasFederationColumns, &$created, &$updated, &$unchanged): void {
            foreach ($parsed as $item) {
                $existing = FarmerOrganization::query()
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($item['name'])])
                    ->where('barangay', $item['barangay'])
                    ->first()
                    ?? FarmerOrganization::query()
                        ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($item['name'])])
                        ->first();

                $attributes = [
                    'name' => $item['name'],
                    'barangay' => $item['barangay'],
                    'area_hectares' => $item['area_hectares'],
                    'farmer_count' => $item['farmer_count'],
                ];

                if ($hasFederationColumns) {
                    $attributes['registration_no'] = $item['registration_no'];
                    $attributes['date_registered'] = $item['date_registered'];
                }

                $alreadyConfirmed = $existing !== null && $hasFederationColumns && $existing->farm_type_confirmed_at !== null;
                if (! $alreadyConfirmed) {
                    $attributes['farm_type'] = $item['farm_type'];
                    if ($hasFederationColumns) {
                        $attributes['farm_type_source'] = self::INFERRED_SOURCE;
                    }
                }

                if ($existing === null) {
                    FarmerOrganization::query()->create($attributes);
                    $created++;
                    continue;
                }

                $existing->fill($attributes);
                if ($existing->isDirty()) {
                    $existing->save();
                    $updated++;
                } else {
                    $unchanged++;
                }
            }
        });

        CacheScope::bump('farmers');
        CacheScope::bump('heat-map-layers');
        app(HeatMapLayerService::class)->forgetCache();

        return [
            'rows' => count($parsed),
            'created' => $created,
            'updated' => $updated,
            'unchanged' => $unchanged,
            'irrigated_ha' => $computedIrrigated,
            'rainfed_ha' => $computedRainfed,
            'total_ha' => round($computedIrrigated + $computedRainfed, 2),
        ];
    }

    private function resolvePath(?string $path): string
    {
        $target = trim((string) ($path ?: self::DEFAULT_PATH));
        if (str_starts_with($target, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $target) === 1) {
            return $target;
        }

        return base_path($target);
    }

    private function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return CarbonImmutable::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $normalized = preg_replace('/\bSept\./i', 'Sep', $text) ?? $text;
        $normalized = preg_replace('/([A-Za-z]+)\.(\d)/', '$1 $2', $normalized) ?? $normalized;
        $normalized = str_replace('.', '', $normalized);

        return rescue(fn (): string => CarbonImmutable::parse($normalized)->toDateString(), null, false);
    }
}
