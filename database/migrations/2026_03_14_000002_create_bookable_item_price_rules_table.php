<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookable_item_price_rules', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('bookable_item_id');
            $table->unsignedBigInteger('business_id')->nullable();
            $table->unsignedBigInteger('platform_service_id')->nullable();

            $table->string('rule_type', 50)->default('date_range');
            // default / weekday / date_range / season / special_day

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->unsignedTinyInteger('weekday')->nullable();
            // 0=Sunday ... 6=Saturday (اختياري لاحقاً)

            $table->string('price_type', 20)->default('fixed');
            // fixed / delta / percent

            $table->decimal('price_value', 14, 2)->default(0);
            $table->char('currency', 3)->default('EGP');

            $table->unsignedInteger('min_quantity')->nullable();
            $table->unsignedInteger('max_quantity')->nullable();

            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);

            $table->string('title', 150)->nullable();
            $table->text('notes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

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

            $table->index(['bookable_item_id', 'is_active'], 'bipr_bookable_active_idx');
            $table->index(['bookable_item_id', 'rule_type'], 'bipr_bookable_rule_type_idx');
            $table->index(['bookable_item_id', 'start_date', 'end_date'], 'bipr_bookable_range_idx');
            $table->index(['weekday'], 'bipr_weekday_idx');
            $table->index(['priority'], 'bipr_priority_idx');
            $table->index(['business_id'], 'bipr_business_idx');
            $table->index(['platform_service_id'], 'bipr_platform_service_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookable_item_price_rules');
    }
};
