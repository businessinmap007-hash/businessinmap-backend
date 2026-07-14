<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Preparation sub-status for placed orders, so the app can show progress
 * between `pending` and `completed`: null (placed) → accepted → preparing →
 * ready. Kept as a SEPARATE column from `status` on purpose — the delivery and
 * handover flows key off `status = pending`, so overloading it would break them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('prep_status', 20)->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('prep_status');
        });
    }
};
