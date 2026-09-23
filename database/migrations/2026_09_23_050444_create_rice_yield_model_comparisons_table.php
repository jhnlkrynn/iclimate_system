<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'rice_yield_model_comparisons',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Who Triggered the Prediction
                |--------------------------------------------------------------------------
                */

                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Prediction Period
                |--------------------------------------------------------------------------
                */

                $table->unsignedSmallInteger(
                    'prediction_year'
                );

                $table->unsignedTinyInteger(
                    'prediction_month'
                );

                /*
                |--------------------------------------------------------------------------
                | Production Model
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'production_prediction',
                    10,
                    4
                );

                $table->string(
                    'production_model',
                    150
                )->default(
                    'Current Production Model'
                );

                /*
                |--------------------------------------------------------------------------
                | Candidate Model
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'candidate_prediction',
                    10,
                    4
                )->nullable();

                $table->string(
                    'candidate_model',
                    150
                )->default(
                    'iClimate Candidate Random Forest'
                );

                $table->boolean(
                    'candidate_success'
                )->default(
                    false
                );

                $table->string(
                    'candidate_status',
                    50
                )->default(
                    'Testing Only'
                );

                /*
                |--------------------------------------------------------------------------
                | Comparison
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'difference',
                    10,
                    4
                )->nullable();

                $table->decimal(
                    'absolute_difference',
                    10,
                    4
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Candidate Weather Inputs
                |--------------------------------------------------------------------------
                */

                $table->decimal(
                    'rainfall',
                    12,
                    4
                )->nullable();

                $table->decimal(
                    'tmax',
                    8,
                    4
                )->nullable();

                $table->decimal(
                    'tmin',
                    8,
                    4
                )->nullable();

                $table->decimal(
                    'humidity',
                    8,
                    4
                )->nullable();

                $table->decimal(
                    'wind_speed',
                    10,
                    4
                )->nullable();

                $table->decimal(
                    'wind_direction',
                    10,
                    4
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Weather Model Metadata
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'weather_basis',
                    100
                )->default(
                    'Ambulong'
                );

                $table->string(
                    'weather_model',
                    180
                )->nullable();

                $table->string(
                    'weather_training_from',
                    20
                )->nullable();

                $table->string(
                    'weather_training_to',
                    20
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Error / Diagnostics
                |--------------------------------------------------------------------------
                */

                $table->text(
                    'candidate_error'
                )->nullable();

                $table->string(
                    'source',
                    100
                )->default(
                    'Shadow Comparison'
                );

                $table->json(
                    'metadata'
                )->nullable();

                $table->timestamps();

                /*
                |--------------------------------------------------------------------------
                | Short Custom Index Names
                |--------------------------------------------------------------------------
                |
                | MySQL limits index / identifier names to 64 characters.
                |
                */

                $table->index(
                    [
                        'prediction_year',
                        'prediction_month',
                    ],
                    'rymc_year_month_idx'
                );

                $table->index(
                    'candidate_success',
                    'rymc_success_idx'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'rice_yield_model_comparisons'
        );
    }
};