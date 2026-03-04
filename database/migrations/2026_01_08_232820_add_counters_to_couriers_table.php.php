<?php

// database/migrations/2026_01_09_000001_add_counters_to_couriers_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->unsignedInteger('accepted_count')->default(0)->after('location_lng');
            $table->unsignedInteger('delivered_count')->default(0)->after('accepted_count');
            $table->unsignedInteger('cancelled_count')->default(0)->after('delivered_count');

            // اختياري: total_ops لو تحبه مخزن
            $table->unsignedInteger('total_ops')->default(0)->after('cancelled_count');
        });
    }

    public function down(): void
    {
        Schema::table('couriers', function (Blueprint $table) {
            $table->dropColumn(['accepted_count','delivered_count','cancelled_count','total_ops']);
        });
    }
};
