<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wallet top-up intents: the real-money-in ledger that sits in front of the
 * points wallet. A row is created `pending` when the customer starts a top-up,
 * flipped to `paid` by the gateway's server-to-server callback (which then
 * credits the wallet via WalletService::deposit), or `failed`/`expired`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('gateway', 40)->default('fawry');
            // Our reference sent to the gateway (unique, = the row id as string).
            $table->string('merchant_ref', 64)->nullable()->unique();
            // The gateway's own transaction reference, returned in the callback.
            $table->string('gateway_ref', 100)->nullable()->index();
            $table->string('method', 40)->nullable(); // CARD / PAYATFAWRY / APPLE_PAY ...
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
        Schema::dropIfExists('wallet_topups');
    }
};
