<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether an order has been paid THROUGH the platform gateway. Defaults
 * `unpaid`; the merchant-payment callback stamps `paid` (+ paid_at) on the linked
 * order. Cash orders stay `unpaid` in the system — they settle in person, off
 * the platform — so `paid` specifically means a completed gateway payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->default('unpaid')->index()->after('payment_method');
            $table->timestamp('paid_at')->nullable()->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'paid_at']);
        });
    }
};
