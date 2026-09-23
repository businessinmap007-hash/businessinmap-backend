<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Today's walk-in queue: separate from scheduled_at («الدور منفصل عن الوقت» —
 * المالك). checked_in_at marks arrival (via QR, {@see
 * ClinicAppointmentService::confirmCheckin}); queue_priority_override is the
 * secretary's manual "call this one now" ({@see ClinicAppointmentService
 * ::callNow}), which always wins over the automatic pattern.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->string('checkin_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('checked_in_at')->nullable()->after('checkin_token');
            $table->boolean('is_walk_in')->default(false)->after('checked_in_at');
            $table->integer('queue_priority_override')->nullable()->after('is_walk_in');
        });

        // «قاعدة عامة تلقائية للعيادة»: an ordered list of bookable_item_type
        // keys (booking_examination, booking_consultation, …) the queue
        // cycles through when calling waiting patients — e.g. 1 كشف ثم 2
        // استشارة. Null means plain first-checked-in-first-called.
        Schema::table('users', function (Blueprint $table) {
            $table->json('clinic_queue_pattern')->nullable()->after('medical_title');
        });
    }

    public function down(): void
    {
        Schema::table('clinic_appointments', function (Blueprint $table) {
            $table->dropColumn(['checkin_token', 'checked_in_at', 'is_walk_in', 'queue_priority_override']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('clinic_queue_pattern');
        });
    }
};
