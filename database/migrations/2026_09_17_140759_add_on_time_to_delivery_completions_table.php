<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_completions', function (Blueprint $table) {
            // Null when the order never carried a delivery_eta_at (the driver
            // never sent one) - "on time" is only ever claimed against a real
            // promise, never assumed true by default.
            $table->boolean('on_time')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_completions', function (Blueprint $table) {
            $table->dropColumn('on_time');
        });
    }
};
