<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An OPTIONAL arrival QR for a clinic or hospital (owner 2026-10-15): the business prints/shows it, the patient scans it
 * with the platform's scanner core to confirm he arrived — which is what activates his turn in the queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('arrival_qr_enabled')->default(false);
            $table->string('arrival_qr_token', 40)->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['arrival_qr_enabled', 'arrival_qr_token']);
        });
    }
};
