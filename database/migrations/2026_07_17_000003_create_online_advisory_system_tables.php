<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_weather_data', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->string('location_name')->default('Lian, Batangas');
            $table->unsignedBigInteger('barangay_id')->nullable()->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('forecast_date')->index();
            $table->time('forecast_time')->nullable();
            $table->integer('weather_code')->nullable();
            $table->decimal('temperature', 8, 2)->nullable();
            $table->decimal('temperature_max', 8, 2)->nullable();
            $table->decimal('temperature_min', 8, 2)->nullable();
            $table->decimal('humidity', 8, 2)->nullable();
            $table->decimal('rainfall_mm', 10, 2)->nullable();
            $table->decimal('precipitation_probability', 8, 2)->nullable();
            $table->decimal('wind_speed', 8, 2)->nullable();
            $table->decimal('soil_temperature', 8, 2)->nullable();
            $table->decimal('soil_moisture', 8, 4)->nullable();
            $table->decimal('evapotranspiration_mm', 8, 2)->nullable();
            $table->json('raw_response')->nullable();
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
            $table->index(['source', 'forecast_date']);
        });

        Schema::create('advisory_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('advisory_type')->index();
            $table->text('description')->nullable();
            $table->string('severity')->index();
            $table->unsignedInteger('priority')->default(50)->index();
            $table->json('conditions');
            $table->text('recommendation');
            $table->string('source_name')->nullable();
            $table->string('source_reference')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('requires_crop_data')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE planting_advisories MODIFY type VARCHAR(50) NOT NULL');
            DB::statement("ALTER TABLE planting_advisories MODIFY status VARCHAR(50) NOT NULL DEFAULT 'pending_review'");
        }

        Schema::table('planting_advisories', function (Blueprint $table) {
            $table->string('advisory_type')->nullable()->after('type')->index();
            $table->text('summary')->nullable()->after('content');
            $table->text('message')->nullable()->after('summary');
            $table->text('recommended_action')->nullable()->after('message');
            $table->string('severity')->default('information')->after('recommended_action')->index();
            $table->unsignedInteger('priority')->default(50)->after('severity');
            $table->unsignedBigInteger('target_barangay_id')->nullable()->after('target_barangay')->index();
            $table->string('target_scope')->default('municipality')->after('target_barangay_id');
            $table->string('source')->nullable()->after('target_scope');
            $table->string('source_url')->nullable()->after('source');
            $table->foreignId('weather_data_id')->nullable()->after('source_url')->constrained('external_weather_data')->nullOnDelete();
            $table->foreignId('advisory_rule_id')->nullable()->after('weather_data_id')->constrained('advisory_rules')->nullOnDelete();
            $table->string('generation_key')->nullable()->unique()->after('advisory_rule_id');
            $table->boolean('generated_automatically')->default(false)->after('generation_key');
            $table->boolean('requires_review')->default(false)->after('generated_automatically');
            $table->timestamp('valid_from')->nullable()->after('requires_review')->index();
            $table->timestamp('valid_until')->nullable()->after('valid_from')->index();
            $table->timestamp('published_at')->nullable()->after('valid_until');
            $table->foreignId('approved_by')->nullable()->after('published_at')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->text('rejection_reason')->nullable()->after('approved_at');
            $table->json('metadata')->nullable()->after('rejection_reason');
            $table->index(['advisory_type', 'status']);
            $table->index(['severity', 'status']);
        });

        DB::table('planting_advisories')
            ->whereNull('advisory_type')
            ->orderBy('id')
            ->get()
            ->each(function (object $record): void {
                $createdAt = $record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at) : now();
                $status = match ($record->status) {
                    'Published' => 'published',
                    'Draft' => 'pending_review',
                    default => strtolower((string) $record->status),
                };

                DB::table('planting_advisories')
                    ->where('id', $record->id)
                    ->update([
                        'advisory_type' => strtolower((string) $record->type),
                        'message' => $record->content,
                        'summary' => \Illuminate\Support\Str::limit((string) $record->content, 180),
                        'severity' => 'information',
                        'target_scope' => 'municipality',
                        'source' => 'MAO-Reviewed Advisory',
                        'status' => $status,
                        'published_at' => $status === 'published' ? $createdAt : null,
                        'valid_from' => $createdAt,
                        'valid_until' => $createdAt->copy()->addDays(7),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('planting_advisories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('advisory_rule_id');
            $table->dropConstrainedForeignId('weather_data_id');
            $table->dropColumn([
                'advisory_type',
                'summary',
                'message',
                'recommended_action',
                'severity',
                'priority',
                'target_barangay_id',
                'target_scope',
                'source',
                'source_url',
                'generation_key',
                'generated_automatically',
                'requires_review',
                'valid_from',
                'valid_until',
                'published_at',
                'approved_at',
                'rejection_reason',
                'metadata',
            ]);
        });

        Schema::dropIfExists('advisory_rules');
        Schema::dropIfExists('external_weather_data');
    }
};
