<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «التسوية» of a non-refundable stay (owner, 2026-10-28):
 *
 *  - non_refundable_deposit_percent: the «عربون الجدية» of the non-refundable rate. When the hotel accepts such a booking, this % of
 *    its price is frozen from the guest — and as much from the hotel, its counter guarantee — each side's GUARANTEE first, its wallet
 *    for the rest. Nothing is ever deducted; it unwinds when both sides say «تمت التسوية», when a payment the guest recorded is
 *    confirmed by the hotel, at the end of the stay, or by an arbitrator's ruling. Never by a timer.
 *  - stay_settlement_payments: what the guest says he paid the hotel outside the app — amount, method, reference and a PROOF image, with
 *    the guest's acknowledgement that paying outside the app is on his own responsibility; only the hotel (the payee) confirms it arrived.
 *    Rows are never deleted: they are what an arbitrator reads.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->decimal('non_refundable_deposit_percent', 5, 2)->nullable()->after('non_refundable_percent');
        });

        Schema::create('stay_settlement_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('booking_id')->index();
            $table->unsignedBigInteger('payer_id');
            $table->decimal('amount', 12, 2);
            $table->string('method', 20); // cash | transfer
            $table->string('reference', 120)->nullable();
            $table->string('proof_image');
            $table->timestamp('acknowledged_at');
            $table->string('status', 20)->default('recorded'); // recorded | received | refused
            $table->string('answer_note', 500)->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->unsignedBigInteger('answered_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stay_settlement_payments');

        Schema::table('business_booking_settings', function (Blueprint $table) {
            $table->dropColumn('non_refundable_deposit_percent');
        });
    }
};
