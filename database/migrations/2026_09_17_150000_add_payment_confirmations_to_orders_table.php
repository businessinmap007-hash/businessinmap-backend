<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three independent cash-payment attestations for the no-wallet COD flow:
 * the customer confirms they paid, the merchant confirms they received the
 * order amount, and the driver confirms they received the delivery fee.
 * Each is its own single-actor, one-shot timestamp - not a chain, and not
 * gated on one another (see OrderController::confirmPayment/
 * businessConfirmPayment and DeliveryDispatchService::confirmPaymentReceived).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('customer_payment_confirmed_at')->nullable()->after('delivery_eta_at');
            $table->timestamp('merchant_payment_confirmed_at')->nullable()->after('customer_payment_confirmed_at');
            $table->timestamp('driver_payment_confirmed_at')->nullable()->after('merchant_payment_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['customer_payment_confirmed_at', 'merchant_payment_confirmed_at', 'driver_payment_confirmed_at']);
        });
    }
};
