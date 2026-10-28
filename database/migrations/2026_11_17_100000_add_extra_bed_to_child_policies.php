<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «سرير إضافي بسعره بدل الرفض» (owner, 2026-10-28): a stay with more guests than the room takes gets an extra bed at the hotel's price per
 * night, up to the number of extra beds a room can take; beyond that it is refused. No price written = no extra bed (refused, as before).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('child_policies', function (Blueprint $table) {
            $table->decimal('extra_bed_price', 10, 2)->nullable()->after('meals_follow');
            $table->unsignedTinyInteger('extra_beds_max')->default(1)->after('extra_bed_price');
        });
    }

    public function down(): void
    {
        Schema::table('child_policies', function (Blueprint $table) {
            $table->dropColumn(['extra_bed_price', 'extra_beds_max']);
        });
    }
};
