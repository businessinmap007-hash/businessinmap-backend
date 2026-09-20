<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cash confirmation for bookings: the client says they paid, the business
 * says it received the money. Each confirmation also counts as that party's
 * agreement to release the booking's frozen deposit
 * (BookingController::recordCashConfirmation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->timestamp('client_payment_confirmed_at')->nullable();
            $table->timestamp('business_payment_confirmed_at')->nullable();
            $table->timestamp('payment_settled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['client_payment_confirmed_at', 'business_payment_confirmed_at', 'payment_settled_at']);
        });
    }
};
