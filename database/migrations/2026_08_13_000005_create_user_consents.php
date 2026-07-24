<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's recorded acceptance of a legal document (terms / privacy) at a given
 * version. One row per (user, document, version) — the audit trail proving each
 * account consented when it was created, and to which revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_consents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('document', 40);   // terms | privacy
            $table->string('version', 40);
            $table->timestamp('accepted_at');
            $table->string('ip', 45)->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'document', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_consents');
    }
};
