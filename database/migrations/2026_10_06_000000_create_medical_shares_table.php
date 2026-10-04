<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «التاريخ المرضى يحفظ على الفون وعند مشاركته يقرأ ويعرض للطبيب او الصيدلى — بأمان ومشفر» — المالك،
 * 2026-10-05. The medical file lives on the patient's phone. To show it to a doctor the phone encrypts the
 * part it shares with a fresh key, leaves ONLY the ciphertext here for a few minutes, and puts the key in the
 * QR code it shows. This table never holds anything it can read.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_shares', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->index();
            // base64 of nonce + AES-GCM ciphertext + tag; the key never reaches the server.
            $table->mediumText('ciphertext');
            $table->timestamp('expires_at')->index();
            $table->unsignedInteger('reads')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_shares');
    }
};
