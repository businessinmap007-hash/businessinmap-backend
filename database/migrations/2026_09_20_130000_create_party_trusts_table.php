<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "I trust this user" - a standing, one-directional trust between any two
 * parties of an order (merchant/driver/customer). A row = trusted. Only the
 * merchant->customer direction has a financial effect today (it also
 * vouches via trusted_partners, waiving the order deposit); the others are
 * recorded so a deposit/guarantee requirement can honour them later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_trusts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('truster_id');
            $table->unsignedBigInteger('trusted_id');
            $table->timestamps();

            $table->unique(['truster_id', 'trusted_id']);
            $table->index('trusted_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_trusts');
    }
};
