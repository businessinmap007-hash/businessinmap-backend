<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An account was located by lat/lng alone — no administrative division at
 * all — even though `countries`/`governorates`/`cities` (249/27/1,339 rows)
 * already exist and are already fully served by Api\V2\LocationController
 * for the address book. This just gives a user account the same three ids,
 * so a profile can carry an administrative location alongside its GPS point
 * instead of only the coordinate pair.
 *
 * All three nullable and independently settable: GPS resolves them via
 * CityLocatorService (city -> governorate -> country, in one lookup), but a
 * manual picker can also set them without ever touching latitude/longitude.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('country_id')->nullable()->after('longitude')->constrained()->nullOnDelete();
            $table->foreignId('governorate_id')->nullable()->after('country_id')->constrained()->nullOnDelete();
            $table->foreignId('city_id')->nullable()->after('governorate_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('governorate_id');
            $table->dropConstrainedForeignId('country_id');
        });
    }
};
