<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A menu item could not say what KIND of thing it is.
 *
 * The platform gives «مطعم» fourteen food types — مقبلات، سلطات، شوربة،
 * مشويات، ساندوتشات، بيتزا… — through `category_service_configs`
 * .allowed_item_types, and `menu_items` had no column to record which one an
 * item was. So «مشويات» existed as a permission and never as a heading: the
 * only grouping a customer could see came from `menu_sections`, which the
 * merchant has to type himself, and of which there are ZERO in the database.
 *
 * Nullable on purpose. An item without a type is not wrong — a furniture
 * showroom's heading is its line option («غرفة نوم»), not its item type
 * («قطعة أثاث»), and MenuDiscoveryController falls through to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('item_type', 60)->nullable()->after('menu_section_id');
            $table->index(['business_id', 'item_type'], 'menu_items_business_item_type_idx');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex('menu_items_business_item_type_idx');
            $table->dropColumn('item_type');
        });
    }
};
