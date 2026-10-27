<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NSIC-registered rice varieties (the "Rice" sheet of the NSIC list, php artisan rice-varieties:import) and the ones
 * IT Personnel add by hand. Every trial figure is kept for the four season × crop establishment combinations:
 * tpr_ds, tpr_ws (transplanted, dry / wet season) and dwsr_ds, dwsr_ws (direct wet seeded). A cell written as
 * "109 (100-120)" is stored as 109 with 100 / 120 in _min / _max.
 */
return new class extends Migration
{
    private const COMBOS = ['tpr_ds', 'tpr_ws', 'dwsr_ds', 'dwsr_ws'];

    public function up(): void
    {
        if (Schema::hasTable('rice_varieties')) {
            return;
        }

        Schema::create('rice_varieties', function (Blueprint $table) {
            $table->id();
            // The upsert key: the NSIC registration number, or "name|year" for the old "Philippine Seed Board" rows.
            $table->string('import_key', 191)->unique();
            $table->unsignedInteger('source_no')->nullable();
            $table->unsignedSmallInteger('year_approved')->nullable()->index();
            $table->string('nsic_reg_no', 120)->nullable()->index();
            $table->string('variety_name', 191)->index();
            $table->string('line_name', 191)->nullable();
            $table->string('variety_type', 40)->nullable()->index();
            $table->string('owner_breeder', 191)->nullable();

            foreach (self::COMBOS as $combo) {
                $table->unsignedInteger('yield_'.$combo)->nullable();                // kg/ha (the mean of regional values)
            }
            $table->json('yield_values')->nullable();                                 // raw text and every number, per combo

            foreach ([...self::COMBOS, 'seed_production'] as $combo) {
                foreach (['', '_min', '_max'] as $part) {
                    $table->unsignedSmallInteger('maturity_'.$combo.$part)->nullable();
                }
            }
            foreach (self::COMBOS as $combo) {
                foreach (['', '_min', '_max'] as $part) {
                    $table->decimal('height_'.$combo.$part, 6, 1)->nullable();
                    $table->decimal('tillers_'.$combo.$part, 6, 1)->nullable();
                }
            }

            // One figure per variety for the catalog's sort and filters (first known of TPR WS, TPR DS, DWSR WS, DWSR DS).
            $table->unsignedSmallInteger('maturity_typical')->nullable()->index();
            $table->unsignedInteger('yield_typical')->nullable()->index();

            $table->string('culm_strength', 80)->nullable();
            $table->json('reactions')->nullable();                                    // {pests: {...}, diseases: {...}}
            $table->decimal('amylose_content', 5, 2)->nullable();                     // percent
            $table->text('adaptation')->nullable();
            $table->text('remarks')->nullable();
            $table->string('ecosystem', 40)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('source', 20)->default('nsic_import')->index();           // nsic_import | manual
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rice_varieties');
    }
};
