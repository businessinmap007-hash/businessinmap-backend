<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Opt-in per business: once on, staff.attendance.check-in/out
            // require a scanned attendance_qr_tokens row plus GPS proximity
            // to this business's own latitude/longitude - off by default so
            // no existing business's plain self-service check-in changes.
            $table->boolean('attendance_verification_enabled')->default(false)->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('attendance_verification_enabled');
        });
    }
};
