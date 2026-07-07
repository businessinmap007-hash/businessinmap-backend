<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make item-type ↔ branch a many-to-many relation: an item type can now belong
 * to several branches at once (e.g. "room" under both "hotel" and "residential
 * units"), like a category child under two roots. Replaces the single
 * platform_service_item_types.group_id column.
 *
 * Backfills the pivot from the existing group_id, then drops the column.
 * Organizational only (admin) — no pricing/booking impact.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_service_item_group_type')) {
            Schema::create('platform_service_item_group_type', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')
                    ->constrained('platform_service_item_groups')
                    ->cascadeOnDelete();
                $table->foreignId('item_type_id')
                    ->constrained('platform_service_item_types')
                    ->cascadeOnDelete();
                $table->unique(['group_id', 'item_type_id'], 'psigt_group_type_unique');
                $table->index('item_type_id', 'psigt_type_index');
            });
        }

        // Backfill from the legacy single group_id.
        if (Schema::hasTable('platform_service_item_types')
            && Schema::hasColumn('platform_service_item_types', 'group_id')) {
            DB::table('platform_service_item_types')
                ->whereNotNull('group_id')
                ->orderBy('id')
                ->select(['id', 'group_id'])
                ->chunk(500, function ($rows) {
                    $insert = $rows->map(fn ($r) => [
                        'group_id' => (int) $r->group_id,
                        'item_type_id' => (int) $r->id,
                    ])->all();

                    if ($insert) {
                        DB::table('platform_service_item_group_type')->insertOrIgnore($insert);
                    }
                });

            Schema::table('platform_service_item_types', function (Blueprint $table) {
                $table->dropForeign(['group_id']);
                $table->dropColumn('group_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('platform_service_item_types')
            && ! Schema::hasColumn('platform_service_item_types', 'group_id')) {
            Schema::table('platform_service_item_types', function (Blueprint $table) {
                $table->foreignId('group_id')
                    ->nullable()
                    ->after('platform_service_id')
                    ->constrained('platform_service_item_groups')
                    ->nullOnDelete();
            });

            // Restore one branch per type (the lowest group id) as the primary.
            if (Schema::hasTable('platform_service_item_group_type')) {
                $primary = DB::table('platform_service_item_group_type')
                    ->selectRaw('item_type_id, MIN(group_id) AS group_id')
                    ->groupBy('item_type_id')
                    ->get();

                foreach ($primary as $row) {
                    DB::table('platform_service_item_types')
                        ->where('id', (int) $row->item_type_id)
                        ->update(['group_id' => (int) $row->group_id]);
                }
            }
        }

        Schema::dropIfExists('platform_service_item_group_type');
    }
};
