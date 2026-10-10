<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «النسخة الاحتياطية المشفرة لملفات المرضى» — المالك، 2026-10-11.
 *
 * The clinic's patient files live on the clinic device only. This is the OPTIONAL server copy of them: ONE opaque blob
 * per account — the device gzips the files and seals them with a key made from a passphrase only the clinic knows
 * (PBKDF2 + AES-GCM), so the server holds noise it cannot open and cannot recover. The sealed text is a PRIVATE FILE
 * (megabytes do not belong in a row, and a database's packet limit would refuse them); this table only says where it is
 * and how big. The clinic can equally keep the backup as a file of its own and never use this table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clinic_file_backups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('path', 160);
            $table->unsignedInteger('bytes')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_file_backups');
    }
};
