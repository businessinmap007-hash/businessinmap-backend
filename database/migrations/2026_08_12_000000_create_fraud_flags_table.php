<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Suspected-fraud flags raised from the operation-rating graph.
 *
 * The platform's durable fraud signal is the transaction graph — a user with a
 * high share of disputed or cancelled operations — not the identity, which is
 * cheap to change. This table holds what the scan SUGGESTS. Nothing here fines
 * or bans anyone: an admin reviews a flag and acts (levy a fine, ban), or
 * dismisses it as a false positive so it stops resurfacing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fraud_flags', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();     // one live flag per account
            $table->decimal('score', 5, 4)->default(0);          // 0..1 risk, for ranking
            $table->unsignedInteger('total_operations')->default(0);
            $table->decimal('disputed_ratio', 5, 4)->default(0);
            $table->decimal('cancelled_ratio', 5, 4)->default(0);
            $table->json('reasons')->nullable();                 // which signals fired
            $table->string('status', 16)->default('open')->index(); // open | dismissed
            $table->timestamp('flagged_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_flags');
    }
};
