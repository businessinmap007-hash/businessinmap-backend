<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime-editable payment gateway credentials (Fawry, and future PSPs). Lets an
 * admin paste live gateway codes from the AdminV2 panel without a redeploy or an
 * .env edit. Secret values (security keys) are stored encrypted at rest —
 * `is_encrypted` flags which columns to decrypt on read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // e.g. fawry.merchant_code
            $table->text('value')->nullable();   // ciphertext when is_encrypted
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_settings');
    }
};
