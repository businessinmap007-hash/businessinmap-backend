<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-business retail settings — today just a minimum order amount a
 * wholesaler/retailer may impose on its own catalog listings, mirroring
 * `business_menu_settings.min_order_amount` (see
 * 2026_08_26_000007_add_min_order_and_margin_to_business_menu_settings.php)
 * but kept in its own table since retail and menu are separate channels
 * (see [[menu-vs-retail-dual-channel]]) with no other billing settings in
 * common yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_retail_settings')) {
            Schema::create('business_retail_settings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->unique();
                $table->decimal('min_order_amount', 10, 2)->nullable();
                $table->timestamps();
                $table->foreign('business_id', 'business_retail_settings_business_fk')
                    ->references('id')->on('users')->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_retail_settings');
    }
};
