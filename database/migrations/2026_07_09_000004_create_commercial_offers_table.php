<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the hand-created commercial_offers table. Guarded with hasTable.
 * This is the unified offer/discovery surface: direct offers, reseller offers,
 * and allocation-generated offers (source_type = allocation) all live here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('commercial_offers')) {
            return;
        }

        Schema::create('commercial_offers', function (Blueprint $table) {
            $table->id();
            $table->string('offerable_type', 50);
            $table->unsignedBigInteger('offerable_id');
            $table->unsignedBigInteger('owner_business_id');
            $table->unsignedBigInteger('seller_business_id');
            $table->string('source_type', 50)->default('direct');
            $table->string('audience_type', 20)->default('both');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title_ar', 255)->nullable();
            $table->string('title_en', 255)->nullable();
            $table->decimal('base_price', 12, 2)->default(0);
            $table->decimal('final_price', 12, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->string('discount_type', 30)->nullable();
            $table->decimal('discount_value', 12, 2)->nullable();
            $table->string('availability_mode', 50)->default('instant');
            $table->integer('available_quantity')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('is_refundable')->default(false);
            $table->string('payment_model', 50)->nullable();
            $table->unsignedBigInteger('cancellation_policy_id')->nullable();
            $table->unsignedBigInteger('deposit_policy_id')->nullable();
            $table->unsignedBigInteger('guarantee_policy_id')->nullable();
            $table->decimal('ranking_score', 10, 4)->default(0);
            $table->boolean('is_featured')->default(false);
            $table->dateTime('featured_until')->nullable();
            $table->decimal('boost_score', 10, 4)->default(0);
            $table->string('status', 30)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['offerable_type', 'offerable_id'], 'idx_offer_offerable');
            $table->index('seller_business_id', 'idx_offer_seller');
            $table->index('owner_business_id', 'idx_offer_owner');
            $table->index(['source_type', 'source_id'], 'idx_offer_source');
            $table->index('final_price', 'idx_offer_price');
            $table->index(['status', 'starts_at', 'ends_at'], 'idx_offer_status_period');
            $table->index('audience_type', 'idx_commercial_offers_audience');
            $table->index(['is_featured', 'featured_until'], 'idx_commercial_offers_featured');
            $table->index('boost_score', 'idx_commercial_offers_boost_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_offers');
    }
};
