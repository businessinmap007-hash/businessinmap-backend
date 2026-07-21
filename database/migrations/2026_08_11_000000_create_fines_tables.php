<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The platform fine — a penalty the platform levies on a user for fraud or
 * abuse, OUTSIDE a dispute. (Dispute/arbitration fines already run through
 * arbitration_sessions + dispute_obligations; this is the unilateral one.)
 *
 * The money is handled as freeze → appeal window → deduct, never instant
 * seizure: at levy the amount is locked in the wallet (protective), an appeal
 * window opens, and only an unappealed-or-upheld fine is captured to the
 * treasury as PURPOSE_FINE. A fine the user already consented to as part of a
 * settlement is marked not-appealable — the consent was conditional on the
 * settlement completing, so there is nothing left to appeal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();          // the fined user
            $table->decimal('amount', 12, 2);                        // what was levied
            $table->decimal('frozen_amount', 12, 2)->default(0);     // what we managed to lock
            $table->decimal('collected_amount', 12, 2)->default(0);  // what reached the treasury
            $table->text('reason');
            $table->string('source', 32)->default('admin');          // admin | fraud | settlement
            $table->string('status', 24)->default('frozen')->index(); // frozen|appealed|overturned|upheld|collected|cancelled
            $table->boolean('is_appealable')->default(true);
            $table->timestamp('appeal_deadline_at')->nullable();
            $table->unsignedBigInteger('levied_by')->nullable();     // admin
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();            // overturned/cancelled
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('fine_appeals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('fine_id')->index();
            $table->unsignedBigInteger('user_id');                   // the appellant
            $table->text('statement');
            $table->string('status', 16)->default('pending');        // pending|accepted|rejected
            $table->unsignedBigInteger('decided_by')->nullable();    // admin
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fine_appeals');
        Schema::dropIfExists('fines');
    }
};
