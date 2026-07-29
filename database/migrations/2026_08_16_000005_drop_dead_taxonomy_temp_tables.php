<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Phase A of the services-linking simplification (agreed 2026-07-29): drop the
 * two migration-scratch tables left behind by the options→item_types work.
 *
 * Both are pure leftovers — `temp_category_option_mapping` and
 * `temp_unmatched_category_option_ids` — with ZERO references anywhere in
 * app/, routes/ or seeders (only their own creating migration mentions them).
 * They are part of the "parallel mechanisms" clutter the reorg is removing.
 *
 * Deliberately NOT touched in this phase (both were on the original Phase A
 * list but investigation reversed the call):
 *   - The «⭐» rows in category_children_master are the LEGITIMATE hotel-star
 *     classification (parent #24, 67 businesses classified under them) — not
 *     pollution. They stay.
 *   - users.legacy_category_id / legacy_category_child_id have 0 code refs but
 *     still hold 1750 populated rows (the old→new classification audit trail);
 *     dropping them waits for a dedicated step that backs the mapping up first.
 */
return new class extends Migration
{
    private const TABLES = [
        'temp_category_option_mapping',
        'temp_unmatched_category_option_ids',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Scratch tables — recreate empty shells so the migration is reversible,
        // but the transient mapping data they held is not restored (it was
        // already consumed by the options→item_types migration long ago).
        if (! Schema::hasTable('temp_category_option_mapping')) {
            Schema::create('temp_category_option_mapping', function ($table) {
                $table->id();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('option_id')->nullable();
                $table->unsignedBigInteger('item_type_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('temp_unmatched_category_option_ids')) {
            Schema::create('temp_unmatched_category_option_ids', function ($table) {
                $table->id();
                $table->unsignedBigInteger('option_id')->nullable();
                $table->timestamps();
            });
        }
    }
};
