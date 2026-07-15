<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Classify a trip leg by vehicle/cargo type and open it to international routes.
 *
 * - vehicle_type_id: the platform-standard class (PlatformServiceItemType scoped
 *   to the `schedules` service) — individual/bus/minibus/pickup/refrigerated/
 *   container_20ft/40ft… — filterable in search.
 * - vehicle_label: the business's own free naming (like a menu item), e.g. a
 *   specific vehicle model.
 * - scope + origin/destination country: domestic legs anchor on governorate,
 *   international legs (e.g. LCL container consolidation) anchor on country.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->foreignId('vehicle_type_id')->nullable()->after('mode')
                ->constrained('platform_service_item_types')->nullOnDelete();
            $table->string('vehicle_label', 120)->nullable()->after('vehicle_type_id');

            $table->string('scope', 16)->default('domestic')->after('vehicle_label'); // domestic | international
            $table->unsignedBigInteger('origin_country_id')->nullable()->after('scope');
            $table->unsignedBigInteger('destination_country_id')->nullable()->after('origin_country_id');

            $table->index(['scope', 'origin_country_id', 'destination_country_id'], 'trip_schedules_intl_index');
            $table->index('vehicle_type_id');
        });

        // International legs anchor on country, not governorate → allow NULL.
        DB::statement('ALTER TABLE `trip_schedules` MODIFY `origin_governorate_id` BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE `trip_schedules` MODIFY `destination_governorate_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('trip_schedules', function (Blueprint $table) {
            $table->dropForeign(['vehicle_type_id']);
            $table->dropIndex('trip_schedules_intl_index');
            $table->dropIndex(['vehicle_type_id']);
            $table->dropColumn([
                'vehicle_type_id',
                'vehicle_label',
                'scope',
                'origin_country_id',
                'destination_country_id',
            ]);
        });
    }
};
