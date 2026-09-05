<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A stop can point at a registered business (a clinic, a shop — anywhere a
 * distribution run actually drops off) instead of a hand-typed address. The
 * business's own GPS location is captured here at pick time so navigation is
 * as precise as a coordinate pair rather than a text search, while staying a
 * snapshot: if the business later moves or is deleted, this stop's own
 * label/lat/lng don't silently change or disappear (business_id nulls out on
 * delete, but the coordinates stay put).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->foreignId('business_id')->nullable()->after('trip_schedule_id')->constrained('users')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable()->after('address');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        Schema::table('trip_run_stops', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('address');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });
    }

    public function down(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_id');
            $table->dropColumn(['lat', 'lng']);
        });

        Schema::table('trip_run_stops', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
