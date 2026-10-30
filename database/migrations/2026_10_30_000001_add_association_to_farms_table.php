<?php

use App\Services\Farms\FarmAssociations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * Each farm says whether it belongs to a farmers' association (the one where the farm is):
 *   farmer_organization_id   the association (member)
 *   association_status       member | not_member | pending_other (null: not declared yet)
 *   association_other_name   the name a farmer typed when theirs was not listed (waits for MAO review)
 *   association_note         the MAO's note when that name was not accepted (the farmer sees it)
 *
 * Existing farms: linked to the farmer's primary organization when it is in the farm's barangay; every other farm stays
 * "not declared" (FarmAssociations::backfill(), also `php artisan farms:link-associations`).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('farms') || Schema::hasColumn('farms', 'association_status')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->foreignId('farmer_organization_id')->nullable()->after('farm_type')->constrained('farmer_organizations')->nullOnDelete();
            $table->enum('association_status', ['member', 'not_member', 'pending_other'])->nullable()->after('farmer_organization_id');
            $table->string('association_other_name', 120)->nullable()->after('association_status');
            $table->string('association_note', 500)->nullable()->after('association_other_name');
            $table->index('association_status');
        });

        $counts = FarmAssociations::backfill();
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            (new ConsoleOutput)->writeln(sprintf(
                '  Farm associations: %d linked to the primary organization, %d not declared (%d with no primary organization, %d in another barangay).',
                $counts['linked'], $counts['not_declared'], $counts['no_organization'], $counts['other_barangay'],
            ));
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('farms', 'association_status')) {
            return;
        }

        Schema::table('farms', function (Blueprint $table) {
            $table->dropIndex(['association_status']);
            $table->dropConstrainedForeignId('farmer_organization_id');
            $table->dropColumn(['association_status', 'association_other_name', 'association_note']);
        });
    }
};
