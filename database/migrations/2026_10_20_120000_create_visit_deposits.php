<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «عربون الجدية» for health visits — clinic appointments, lab/radiology orders and hospital procedures.
 *
 * These flows are not `bookings` rows, so the booking engine never saw them. One row per visit keeps the deposit's
 * state (what is owed, wallet-held or outside the platform, and where it stands). The wallet money itself is the
 * ordinary escrow row in `deposits` (target = the visit), exactly as a booking's.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('visit_deposits')) {
            return;
        }

        Schema::create('visit_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->unsignedBigInteger('client_id');
            $table->unsignedBigInteger('business_id');

            // wallet = frozen in the wallet(s) · external = paid straight to the business, outside the platform
            $table->string('mode', 16);
            $table->decimal('amount', 12, 2)->default(0);          // the customer's deposit
            $table->decimal('counter_amount', 12, 2)->default(0);  // what the business froze against it

            // held · pending (external, not yet confirmed) · received · waived · released · refunded · disputed · resolved
            $table->string('status', 16)->default('held');
            $table->string('method', 16)->nullable();              // cash | transfer — external only
            $table->string('reference', 120)->nullable();

            $table->unsignedBigInteger('deposit_id')->nullable();  // the escrow row, for a wallet deposit
            $table->unsignedBigInteger('dispute_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['subject_type', 'subject_id'], 'visit_deposits_subject_unique');
            $table->index(['client_id', 'status']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visit_deposits');
    }
};
