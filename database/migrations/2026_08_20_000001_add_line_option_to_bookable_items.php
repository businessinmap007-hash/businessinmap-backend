<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which KIND of thing each physical unit is.
 *
 * A unit says its item_type — «حجز إقامة» — and nothing more. That was enough
 * while the item type carried the kind (`single_room`, `suite`, `villa`), but
 * the kinds collapsed onto one key and moved into the line option, so a hotel's
 * six priced stay rows are now told apart by `business_service_prices
 * .line_option_id` alone. The unit could not name one, so room 101 and suite
 * س301 resolved to the same price — whichever row the fallback ladder reached.
 *
 * Nullable on purpose: a business with a single priced row per type (a clinic,
 * a padel court) never needs to say it, and the resolver keeps its old ladder
 * for those.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('bookable_items', 'line_option_id')) {
            return;
        }

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->unsignedBigInteger('line_option_id')->nullable()->after('item_type');

            // The lookup the resolver makes: this business's units of this kind.
            $table->index(['business_id', 'service_id', 'item_type', 'line_option_id'], 'bookable_items_line_lookup_index');

            // Retiring an option must not take the unit with it — the room still
            // exists, it just stops naming its kind until someone renames it.
            $table->foreign('line_option_id')->references('id')->on('options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('bookable_items', 'line_option_id')) {
            return;
        }

        Schema::table('bookable_items', function (Blueprint $table) {
            $table->dropForeign(['line_option_id']);
            $table->dropIndex('bookable_items_line_lookup_index');
            $table->dropColumn('line_option_id');
        });
    }
};
