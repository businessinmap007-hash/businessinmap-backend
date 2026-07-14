<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operation-based rating (objective reputation): every completed / cancelled /
 * disputed operation feeds each party's counts, exposed as percentages
 * (success% / cancel% / dispute%). Universal — covers ALL users and ALL
 * operation types (bookings + menu/delivery orders), independent of the
 * guarantee trust_score (which stays for guarantee-level gating).
 *
 * Two tables:
 *  - user_operation_ratings: the per-user, per-role aggregate.
 *  - rating_outcome_events:  an idempotency ledger so the same outcome for the
 *    same operation+party is only counted once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_operation_ratings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('role', 20); // client | business
            $table->unsignedInteger('total_operations')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('cancelled_count')->default(0);
            $table->unsignedInteger('disputed_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index('user_id');
        });

        Schema::create('rating_outcome_events', function (Blueprint $table) {
            $table->id();
            $table->string('operation_type', 30); // booking | order
            $table->unsignedBigInteger('operation_id');
            $table->unsignedBigInteger('ratee_user_id');
            $table->string('role', 20);           // client | business
            $table->string('outcome', 20);        // success | cancelled | disputed
            $table->timestamps();

            // One count per operation+party+outcome — makes recording idempotent.
            $table->unique(['operation_type', 'operation_id', 'ratee_user_id', 'outcome'], 'rating_event_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rating_outcome_events');
        Schema::dropIfExists('user_operation_ratings');
    }
};
