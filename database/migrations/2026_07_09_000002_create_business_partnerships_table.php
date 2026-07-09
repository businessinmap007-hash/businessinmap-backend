<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the hand-created business_partnerships table so a fresh DB / CI can
 * rebuild it. Guarded with hasTable — no-op where the table already exists.
 * A partnership is a directional pair (owner -> partner) with a relationship
 * type; there is intentionally NO unique (owner, partner) constraint, so an
 * owner may partner with many businesses.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_partnerships')) {
            return;
        }

        Schema::create('business_partnerships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_business_id');
            $table->unsignedBigInteger('partner_business_id');
            $table->string('relationship_type', 50)->default('hotel_allotment');
            $table->string('status', 30)->default('pending');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('approval_required')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->json('terms')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('owner_business_id', 'idx_bp_owner');
            $table->index('partner_business_id', 'idx_bp_partner');
            $table->index(['relationship_type', 'status'], 'idx_bp_type_status');
            $table->index(['starts_at', 'ends_at'], 'idx_bp_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partnerships');
    }
};
