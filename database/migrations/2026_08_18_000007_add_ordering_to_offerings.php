<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a merchant say which of his offerings comes first.
 *
 * Every list of what a business sells was ordered by something the business had
 * no say in — id descending on the owner's own screens, price ascending in
 * discovery. A restaurant could not put its signature dish at the top of its
 * own menu section, and a clinic could not lead with the consultation most
 * patients come for.
 *
 * `sort_order` is the merchant's sequence; `is_featured` lifts a row above it.
 * menu_items already had sort_order, so it only gains the flag.
 *
 * Scope worth stating: these order a business's offerings AMONG THEMSELVES.
 * They must not reorder a cross-business discovery list — if a merchant could
 * outrank a competitor by ticking a box, every box gets ticked and the ordering
 * stops meaning anything. There they are a tie-break within one business only.
 */
return new class extends Migration
{
    private const TABLES = [
        'business_service_prices' => ['sort_order', 'is_featured'],
        'menu_items' => ['is_featured'],
        'business_catalog_listings' => ['sort_order', 'is_featured'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        continue;
                    }

                    if ($column === 'sort_order') {
                        $blueprint->unsignedInteger('sort_order')->default(0);
                    } else {
                        $blueprint->boolean('is_featured')->default(false);
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) use ($table, $columns) {
                foreach ($columns as $column) {
                    // menu_items.sort_order predates this migration — leave it
                    if ($column === 'sort_order' && $table === 'menu_items') {
                        continue;
                    }

                    if (Schema::hasColumn($table, $column)) {
                        $blueprint->dropColumn($column);
                    }
                }
            });
        }
    }
};
