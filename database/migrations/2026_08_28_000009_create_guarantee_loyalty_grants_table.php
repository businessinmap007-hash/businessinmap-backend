<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per (user, guarantee level) ever — tracks how much of that level's
 * one-time fee-discount allowance has been used. Deliberately never deleted
 * on downgrade/cancel: a user cancelling and re-buying the same level must
 * NOT get a fresh allowance, or the discount becomes a repeatable exploit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guarantee_loyalty_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('guarantee_level_id')->constrained('guarantee_levels')->cascadeOnDelete();
            $table->decimal('discount_given', 10, 2)->default(0);
            $table->timestamp('exhausted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'guarantee_level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guarantee_loyalty_grants');
    }
};
