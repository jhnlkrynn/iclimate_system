<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel revision 4.2: the model's predicted yield next to the recorded (actual) yield, per month, for the whole
 * municipality of Lian (barangay null) or one barangay. Both in t/ha. Filled by `php artisan iclimate:import-yield-backtest`
 * from python_scripts/backtest_candidate_yield.py's output, and by any later saved prediction that gets its actual.
 * `split` says whether the month was used to train the model (TRAIN, in-sample) or held out (TEST, out-of-sample).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipal_yield_predictions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('month');
            $table->string('season', 10)->index();          // Wet / Dry (App\Support\RiceSeason)
            $table->string('barangay')->nullable()->index(); // null = all of Lian
            $table->decimal('predicted_yield', 8, 4);         // t/ha
            $table->decimal('actual_yield', 8, 4)->nullable(); // t/ha
            $table->string('split', 10)->nullable();          // TRAIN / TEST
            $table->string('model', 120);
            $table->string('source', 160)->nullable();
            $table->timestamps();

            $table->unique(['year', 'month', 'barangay', 'model'], 'municipal_yield_prediction_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipal_yield_predictions');
    }
};
