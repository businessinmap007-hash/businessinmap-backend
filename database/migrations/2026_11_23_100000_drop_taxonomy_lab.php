<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The Taxonomy Lab is gone (owner, 2026-10-29). It was a sandbox clone of the services and options tables (the `*_new` tables) and
 * the hierarchical lists built over it (`lab_lists`, `lab_list_items`), paused since 2026-07-30 and untouched since 2026-08-15. The
 * live tables were reorganised in place after it (option roles, menu shapes, the health remodel, the dead-weight sweeps), so the clone
 * was months stale, nothing read it, and its look-alike table and column names had already misled a cleanup once. Its lists were
 * checked against the live taxonomy before this ran.
 *
 * Not reversible: the clone's rows are not kept.
 */
return new class extends Migration
{
    private const TABLES = [
        'lab_list_items',
        'lab_lists',
        'category_child_option_new',
        'options_new',
        'option_groups_new',
        'platform_service_item_group_type_new',
        'platform_service_item_groups_new',
        'platform_service_item_types_new',
        'platform_services_new',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // the clone is not rebuilt: its source of truth is the live taxonomy
    }
};
