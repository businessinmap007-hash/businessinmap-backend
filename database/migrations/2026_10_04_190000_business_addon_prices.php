<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «طريقة طهى السمك لدى التاجر فى التسعير: المشوى 50 المقلى 80 الصينية 100 للكيلو — العميل اختار كيلو
 * ونص: 450 سمك + صينية 150 = 600» — المالك، 2026-10-04.
 *
 * A cooking method is not a second price of the fish: it is a SERVICE of the shop, priced per unit of
 * what is bought and added on top. The shop prices its services ONCE (`business_addon_prices`); each
 * of its items carries them as a single-choice extra group, so the cart, the customer's sheet and the
 * invoice already know how to add them (unit price + extras, times the quantity).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('business_addon_prices')) {
            Schema::create('business_addon_prices', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id');
                $table->unsignedBigInteger('option_id');
                $table->decimal('price', 10, 2)->default(0);
                $table->timestamps();
                $table->unique(['business_id', 'option_id']);
            });
        }
        if (! Schema::hasColumn('menu_item_extra_groups', 'source_group_id')) {
            Schema::table('menu_item_extra_groups', function (Blueprint $table) {
                // The option group («طريقة الطهي») this extra group was made from, when the shop's own price list made it.
                $table->unsignedBigInteger('source_group_id')->nullable()->index();
            });
        }
        if (! Schema::hasColumn('menu_item_extras', 'source_option_id')) {
            Schema::table('menu_item_extras', function (Blueprint $table) {
                $table->unsignedBigInteger('source_option_id')->nullable()->index();
            });
        }

        // The fish shop's cooking method is an add-on, not a price axis.
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        $group = (int) DB::table('option_groups')->where('name_ar', 'طريقة الطهي')->value('id');
        if ($menu && $group) {
            DB::table('service_option_group_placements')
                ->where('platform_service_id', $menu)->where('option_group_id', $group)->where('usage', 'price_variant')
                ->update(['usage' => 'addon', 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
    }
};
