<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a merchant payment to the order it settles. When a customer checks out a
 * cart with an online payment method, the gateway payment is created for the
 * order's total and points back at it here, so the order and its money-in stay
 * connected (admin oversight, polling, reconciliation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('merchant_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->after('business_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('merchant_payments', function (Blueprint $table) {
            $table->dropColumn('order_id');
        });
    }
};
