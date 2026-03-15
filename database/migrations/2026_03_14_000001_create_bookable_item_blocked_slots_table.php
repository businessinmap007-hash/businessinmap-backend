<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_item_blocked_slots', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bookable_item_id');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('platform_service_id')->nullable();

            $table->string('block_type', 50)->default('manual');
            // manual / maintenance / holiday / booking_hold / system / admin

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->string('reason', 255)->nullable();
            $table->text('notes')->nullable();

            // Optional polymorphic source
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->boolean('is_active')->default(true);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->foreign('bookable_item_id')
                ->references('id')
                ->on('bookable_items')
                ->cascadeOnDelete();

            $table->foreign('business_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->foreign('platform_service_id')
                ->references('id')
                ->on('platform_services')
                ->nullOnDelete();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['bookable_item_id', 'is_active'], 'bis_bookable_active_idx');
            $table->index(['bookable_item_id', 'starts_at', 'ends_at'], 'bis_bookable_range_idx');
            $table->index(['block_type'], 'bis_block_type_idx');
            $table->index(['source_type', 'source_id'], 'bis_source_idx');
            $table->index(['business_id'], 'bis_business_idx');
            $table->index(['platform_service_id'], 'bis_platform_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_item_blocked_slots');
    }
};
