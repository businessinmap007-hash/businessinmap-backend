<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «النسخة الاحتياطية المشفرة» of the medical file kept on the phone — المالك، 2026-10-05. One opaque blob per
 * account: the phone encrypts the file with a key made from a passphrase only the patient knows (PBKDF2 +
 * AES-GCM) and keeps the passphrase nowhere. We cannot read it, and cannot recover it if the passphrase is
 * forgotten — that is the price of «we cannot read it».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            // JSON text the phone wrote: {v, kdf, iterations, salt, data} — all base64, nothing readable.
            $table->mediumText('blob');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_backups');
    }
};
