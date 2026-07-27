<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records when a pre-visit reminder was sent for a confirmed appointment, so the
 * scheduled reminder job never notifies the same appointment twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dateTime('reminded_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn('reminded_at');
        });
    }
};
