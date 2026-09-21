<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The weekdays a company ships on this route (0 = Sunday ... 6 = Saturday);
 * null means every day. A merchant only sees companies running the route
 * today or tomorrow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->json('days')->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('shipping_rates', function (Blueprint $table) {
            $table->dropColumn('days');
        });
    }
};
