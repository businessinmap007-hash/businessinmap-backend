<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime-editable push-notification credentials (Firebase / FCM). Mirrors
 * payment_settings: an admin pastes the Firebase service-account JSON from the
 * AdminV2 panel without a redeploy or an .env edit. The JSON holds the private
 * key, so it is stored encrypted at rest — `is_encrypted` flags decrypt-on-read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();     // e.g. firebase.service_account_json
            $table->text('value')->nullable();   // ciphertext when is_encrypted
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_settings');
    }
};
