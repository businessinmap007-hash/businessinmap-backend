<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generic, append-only log of who did what on a business's behalf — the
 * "end of shift" review a business owner needs when a delegated staff member
 * (a clinic secretary, a shop/restaurant worker) handles orders, bookings, or
 * whatever other capability comes next. Deliberately capability-agnostic: any
 * business.member-gated controller can write one row with StaffActivityLogger,
 * so this table never needs a schema change to cover a new surface.
 *
 * user_id is always the REAL acting account — the staff member if one is
 * acting, the owner if they did it themselves — never the business id alone,
 * which is what every other event/notification pipeline records instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->unsignedBigInteger('user_id');
            $table->string('capability', 40);
            $table->string('action', 60);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['business_id', 'created_at']);
            $table->index(['business_id', 'user_id']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_logs');
    }
};
