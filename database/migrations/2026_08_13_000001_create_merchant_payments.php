<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Customer→merchant payment intents. A customer pays a merchant for an
 * order/service through the gateway; when sub-account routing is on and the
 * merchant is configured, the charge is billed to the MERCHANT's Fawry account
 * (routed_to = merchant), otherwise the platform account. Settled `paid` by the
 * gateway callback — no platform points wallet is touched (the money is the
 * merchant's, off-platform). Mirrors wallet_topups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->index();  // the paying customer
            $table->foreignId('business_id')->index();  // the merchant being paid
            $table->string('gateway', 40)->default('fawry');
            $table->string('routed_to', 20)->default('platform'); // merchant | platform
            $table->string('merchant_ref', 64)->nullable()->unique();
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->string('method', 40)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('EGP');
            $table->string('status', 20)->default('pending')->index();
            $table->json('meta')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payments');
    }
};
