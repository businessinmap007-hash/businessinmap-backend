<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freezes what an order line was, at the moment it was ordered.
 *
 * An order line points at a menu item and looks its name up live, so «غرفة نوم
 * — مودرن» would silently become «غرفة نوم — كلاسيك» the day the merchant
 * re-tagged that item, and would lose its name entirely if the item were
 * deleted. The price on the line is already a snapshot for exactly this
 * reason; what the line WAS deserves the same treatment.
 *
 * Nullable: lines whose offering never named itself have nothing to freeze,
 * and they keep falling back to the item's own name.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('order_items', 'offering_label')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('offering_label', 255)->nullable()->after('offering_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('order_items', 'offering_label')) {
            return;
        }

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('offering_label');
        });
    }
};
