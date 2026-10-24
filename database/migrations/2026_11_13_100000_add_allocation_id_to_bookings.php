<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * An allotment is rooms PER NIGHT (owner, 2026-10-24): «٥ غرف كل ليلة من ١ ديسمبر إلى ٣١». Whether a night has a room left is read from the
 * bookings that sit on it, so a booking has to say which allocation it was sold from — in a column, not buried in its meta.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('bookings', 'allocation_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->unsignedBigInteger('allocation_id')->nullable()->after('bookable_id')->index();
            });
        }

        // bookings already sold from an allocation say so in their meta (`_allocation.id`, until it was released)
        try {
            DB::statement("UPDATE bookings SET allocation_id = CAST(JSON_UNQUOTE(JSON_EXTRACT(meta, '$._allocation.id')) AS UNSIGNED) WHERE allocation_id IS NULL AND meta IS NOT NULL AND JSON_VALID(meta) AND JSON_EXTRACT(meta, '$._allocation.id') IS NOT NULL AND JSON_UNQUOTE(JSON_EXTRACT(meta, '$._allocation.phase')) <> 'released'");
        } catch (\Throwable $e) {
            // nothing to carry over on a database that cannot read the meta as JSON
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bookings', 'allocation_id')) {
            Schema::table('bookings', function (Blueprint $table) {
                $table->dropColumn('allocation_id');
            });
        }
    }
};
