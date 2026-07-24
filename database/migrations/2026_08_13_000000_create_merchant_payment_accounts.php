<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-merchant payment-gateway sub-accounts. Fawry lets the platform's master
 * account fan out into many sub-accounts (one per merchant); when the feature is
 * on, a customer's payment for a merchant's order is routed to THAT merchant's
 * Fawry sub-account instead of the platform account. Each row holds the
 * merchant's own gateway credentials — the security key is stored encrypted at
 * rest (Laravel `encrypted` cast on the model), never in plaintext.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchant_payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id');
            $table->string('gateway')->default('fawry');
            $table->string('merchant_code')->nullable();   // the merchant's own sub-account code
            $table->text('security_key')->nullable();       // ciphertext (encrypted cast)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['business_id', 'gateway']);
            $table->index('business_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_payment_accounts');
    }
};
