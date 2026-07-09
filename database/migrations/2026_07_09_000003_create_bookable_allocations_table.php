<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reproduces the hand-created bookable_allocations table. Guarded with hasTable.
 * An allocation carves a quantity of an owner's bookable_item out to a
 * partnership; the controller syncs it into a commercial_offers row where the
 * partner is the seller (contract_price + markup).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bookable_allocations')) {
            return;
        }

        Schema::create('bookable_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partnership_id');
            $table->unsignedBigInteger('owner_business_id');
            $table->unsignedBigInteger('partner_business_id');
            $table->unsignedBigInteger('bookable_item_id');
            $table->unsignedBigInteger('platform_service_id')->nullable();
            $table->string('allocation_type', 50)->default('non_guaranteed');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->integer('quantity_total')->default(0);
            $table->integer('quantity_sold')->default(0);
            $table->integer('quantity_reserved')->default(0);
            $table->integer('quantity_released')->default(0);
            $table->integer('release_days_before')->default(0);
            $table->integer('min_nights')->nullable();
            $table->integer('max_nights')->nullable();
            $table->decimal('contract_price', 12, 2)->default(0);
            $table->string('currency', 10)->default('EGP');
            $table->string('markup_type', 30)->nullable();
            $table->decimal('markup_value', 12, 2)->nullable();
            $table->string('status', 30)->default('active');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('partnership_id', 'idx_alloc_partnership');
            $table->index(['owner_business_id', 'partner_business_id'], 'idx_alloc_owner_partner');
            $table->index('bookable_item_id', 'idx_alloc_bookable');
            $table->index('platform_service_id', 'idx_alloc_service');
            $table->index(['status', 'starts_at', 'ends_at'], 'idx_alloc_status_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_allocations');
    }
};
