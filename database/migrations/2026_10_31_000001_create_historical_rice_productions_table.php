<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_rice_productions', function (Blueprint $table): void {
            $table->id();
            // Keep the source spelling: TAN-AG SAN DIEGO is intentionally distinct from SAN DIEGO.
            $table->string('barangay');
            $table->string('season', 2);
            $table->unsignedSmallInteger('season_year');
            $table->string('season_label');
            $table->decimal('area_planted_hectares', 14, 8)->nullable();
            $table->decimal('average_yield_t_per_hectare', 14, 8)->nullable();
            $table->decimal('total_production_tons', 16, 8)->nullable();
            $table->string('source_filename')->nullable();
            $table->string('source_sheet')->nullable();
            $table->unsignedSmallInteger('source_row')->nullable();
            $table->string('source_units')->default('area: ha; yield: t/ha; production: tons');
            $table->timestamps();

            $table->unique(['barangay', 'season', 'season_year'], 'historical_rice_production_key');
            $table->index(['barangay', 'season', 'season_year']);
        });

        $rows = [
            // Source workbook: RICE-PRODUCTION (LIAN).xlsx. The last sheet is labelled DS 2025-2026 per the request;
            // its cell A1 says WS 2025-2026, which is retained only as source_sheet provenance below.
            ['WS', 2024, 'WS 2024', 'KAPITO', 96.64, 3.0721233443708607, 296.89, 'WS 2024', 3],
            ['WS', 2024, 'WS 2024', 'MALARUHATAN', 2.9, 2.4862068965517241, 7.21, 'WS 2024', 4],
            ['WS', 2024, 'WS 2024', 'BUNGAHAN', 116.28, 3.4780701754385963, 404.43, 'WS 2024', 5],
            ['WS', 2024, 'WS 2024', 'SAN DIEGO', 22.4, 2.9058035714285717, 65.09, 'WS 2024', 6],
            ['WS', 2024, 'WS 2024', 'MATABUNGKAY', 16.47, 2.5761991499696419, 42.43, 'WS 2024', 7],
            ['WS', 2024, 'WS 2024', 'BALIBAGO', 28.45, 2.5799648506151147, 73.4, 'WS 2024', 8],
            ['WS', 2024, 'WS 2024', 'LUYAHAN', 17.07, 2.9970708845928526, 51.16, 'WS 2024', 9],
            ['WS', 2024, 'WS 2024', 'BINUBUSAN', 63.49, 3.4879508584028978, 221.45, 'WS 2024', 10],
            ['WS', 2024, 'WS 2024', 'TAN-AG SAN DIEGO', 66.3, 3.7701357466063352, 249.96, 'WS 2024', 11],
            ['WS', 2024, 'WS 2024', 'PRENZA', 248.89, 3.7750411828518629, 939.57, 'WS 2024', 12],
            ['DS', 2024, 'DS 2024-2025', 'KAPITO', 132.93, 4.4117204543744828, 586.45, 'DS 2024-2025', 3],
            ['DS', 2024, 'DS 2024-2025', 'MALARUHATAN', 5.9, 4.0779661016949147, 24.06, 'DS 2024-2025', 4],
            ['DS', 2024, 'DS 2024-2025', 'BUNGAHAN', 124.42, 4.2990676740073939, 534.89, 'DS 2024-2025', 5],
            ['DS', 2024, 'DS 2024-2025', 'SAN DIEGO', 18.5, 4.3, 79.55, 'DS 2024-2025', 6],
            ['DS', 2024, 'DS 2024-2025', 'MATABUNGKAY', null, null, null, 'DS 2024-2025', 7],
            ['DS', 2024, 'DS 2024-2025', 'BALIBAGO', 32.6, 4.0411042944785276, 131.74, 'DS 2024-2025', 8],
            ['DS', 2024, 'DS 2024-2025', 'LUYAHAN', 6.6, 2.3803030303030304, 15.71, 'DS 2024-2025', 9],
            ['DS', 2024, 'DS 2024-2025', 'BINUBUSAN', 64.18, 3.4992209411031472, 224.58, 'DS 2024-2025', 10],
            ['DS', 2024, 'DS 2024-2025', 'TAN-AG SAN DIEGO', 44.2, 2.5676470588235292, 113.49, 'DS 2024-2025', 11],
            ['DS', 2024, 'DS 2024-2025', 'PRENZA', 247.14, 4.6800194221898517, 1156.62, 'DS 2024-2025', 12],
            ['WS', 2025, 'WS 2025', 'KAPITO', 139.86, 4.18025168025168, 584.65, 'WS 2025', 3],
            ['WS', 2025, 'WS 2025', 'MALARUHATAN', 17.45, 3.3386819484240688, 58.26, 'WS 2025', 4],
            ['WS', 2025, 'WS 2025', 'BUNGAHAN', 156.8, 4.0281887755102037, 631.62, 'WS 2025', 5],
            ['WS', 2025, 'WS 2025', 'SAN DIEGO', 18.5, 4.1, 75.85, 'WS 2025', 6],
            ['WS', 2025, 'WS 2025', 'MATABUNGKAY', 26.27, 3.1846212409592689, 83.66, 'WS 2025', 7],
            ['WS', 2025, 'WS 2025', 'BALIBAGO', 34.54, 3.1800810654313842, 109.84, 'WS 2025', 8],
            ['WS', 2025, 'WS 2025', 'LUYAHAN', 20.82, 4.3304514889529298, 90.16, 'WS 2025', 9],
            ['WS', 2025, 'WS 2025', 'BINUBUSAN', 73.25, 4.3605460750853249, 319.41, 'WS 2025', 10],
            ['WS', 2025, 'WS 2025', 'TAN-AG SAN DIEGO', 59.4, 3.0787878787878786, 182.88, 'WS 2025', 11],
            ['WS', 2025, 'WS 2025', 'PRENZA', 271.75, 3.3286844526218955, 904.57, 'WS 2025', 12],
            ['DS', 2025, 'DS 2025-2026', 'KAPITO', 54.77, 4.396932627350739, 240.82, 'WS 2025-2026', 3],
            ['DS', 2025, 'DS 2025-2026', 'MALARUHATAN', 6.9, 4.1594202898550723, 28.7, 'WS 2025-2026', 4],
            ['DS', 2025, 'DS 2025-2026', 'BUNGAHAN', 64.16, 3.9582605985037409, 253.962, 'WS 2025-2026', 5],
            ['DS', 2025, 'DS 2025-2026', 'SAN DIEGO', 6.9, 4.2, 28.98, 'WS 2025-2026', 6],
            ['DS', 2025, 'DS 2025-2026', 'MATABUNGKAY', null, null, null, 'WS 2025-2026', 7],
            ['DS', 2025, 'DS 2025-2026', 'BALIBAGO', 16.85, 3.85, 64.8725, 'WS 2025-2026', 8],
            ['DS', 2025, 'DS 2025-2026', 'LUYAHAN', 5.77, 3.43, 19.7911, 'WS 2025-2026', 9],
            ['DS', 2025, 'DS 2025-2026', 'BINUBUSAN', 61.79, 4.1426201650752548, 255.9725, 'WS 2025-2026', 10],
            ['DS', 2025, 'DS 2025-2026', 'TAN-AG SAN DIEGO', 13.7, 3.0810218978102193, 42.21, 'WS 2025-2026', 11],
            ['DS', 2025, 'DS 2025-2026', 'PRENZA', 248, 4.3785483870967745, 1085.8801, 'WS 2025-2026', 12],
        ];

        foreach ($rows as [$season, $year, $label, $barangay, $area, $yield, $production, $sheet, $sourceRow]) {
            DB::table('historical_rice_productions')->insert([
                'barangay' => $barangay, 'season' => $season, 'season_year' => $year, 'season_label' => $label,
                'area_planted_hectares' => $area, 'average_yield_t_per_hectare' => $yield, 'total_production_tons' => $production,
                'source_filename' => 'RICE-PRODUCTION (LIAN).xlsx', 'source_sheet' => $sheet, 'source_row' => $sourceRow,
                'source_units' => 'area: ha; yield: t/ha; production: tons', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_rice_productions');
    }
};
