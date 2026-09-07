<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replaces the free-text `group_key` with a real `extra_group_id` FK to
 * `menu_item_extra_groups` — the group itself now carries selection_type
 * (single/multiple), which a bare string could never express.
 *
 * `group_key` has zero rows using it in production as of this migration, so
 * this is a structural swap, not a data migration: nothing to carry over.
 * An extra with `extra_group_id = null` stays exactly as every row behaves
 * today — a standalone, independently-toggled add-on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_item_extras', function (Blueprint $table) {
            $table->foreignId('extra_group_id')->nullable()->after('menu_item_id')
                ->constrained('menu_item_extra_groups')->nullOnDelete();
        });

        // Belt-and-braces for any environment that did carry rows: fold them
        // into real groups instead of silently discarding the grouping.
        DB::table('menu_item_extras')
            ->whereNotNull('group_key')
            ->where('group_key', '!=', '')
            ->select('menu_item_id', 'group_key')
            ->distinct()
            ->get()
            ->each(function ($row) {
                $groupId = DB::table('menu_item_extra_groups')->insertGetId([
                    'menu_item_id' => $row->menu_item_id,
                    'name_ar' => $row->group_key,
                    'selection_type' => 'multiple',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('menu_item_extras')
                    ->where('menu_item_id', $row->menu_item_id)
                    ->where('group_key', $row->group_key)
                    ->update(['extra_group_id' => $groupId]);
            });

        Schema::table('menu_item_extras', function (Blueprint $table) {
            $table->dropColumn('group_key');
        });
    }

    public function down(): void
    {
        Schema::table('menu_item_extras', function (Blueprint $table) {
            $table->string('group_key', 50)->nullable()->after('menu_item_id')->index();
        });

        DB::table('menu_item_extras')
            ->whereNotNull('extra_group_id')
            ->get(['id', 'extra_group_id'])
            ->each(function ($row) {
                $name = DB::table('menu_item_extra_groups')->where('id', $row->extra_group_id)->value('name_ar');
                if ($name !== null) {
                    DB::table('menu_item_extras')->where('id', $row->id)->update(['group_key' => $name]);
                }
            });

        Schema::table('menu_item_extras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('extra_group_id');
        });
    }
};
