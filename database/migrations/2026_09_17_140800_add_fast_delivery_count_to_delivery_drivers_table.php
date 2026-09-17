<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            // Deliveries confirmed at or before their own delivery_eta_at -
            // the driver's own "توصيل سريع" badge count, same shape as
            // delivered_count.
            $table->unsignedInteger('fast_delivery_count')->default(0)->after('delivered_count');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_drivers', function (Blueprint $table) {
            $table->dropColumn('fast_delivery_count');
        });
    }
};
