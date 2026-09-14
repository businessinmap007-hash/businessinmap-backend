<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Four tables that had no ordering column at all, so every list of them fell
 * back to `id` ascending (creation order) — an option group could reorder
 * itself (option_groups.reorder) but the OPTIONS inside one could not, and a
 * hotel's own "breakfast included / half board / full board" list came out
 * in whatever order those rows happened to be created, not the order a
 * guest reads them in. Same story for countries/governorates/cities: no way
 * to put Egypt first in a 249-country list.
 *
 * Default 0 everywhere: existing behaviour (id-order tiebreak) is
 * unchanged until something is curated.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['options', 'group_id'],
            ['countries', 'id'],
            ['governorates', 'country_id'],
            ['cities', 'governorate_id'],
        ] as [$table, $after]) {
            if (Schema::hasColumn($table, 'sort_order')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($after) {
                $t->integer('sort_order')->default(0)->after($after);
            });
        }
    }

    public function down(): void
    {
        foreach (['options', 'countries', 'governorates', 'cities'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('sort_order');
            });
        }
    }
};
