<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «سيارة من المالك» (#549) is retired (owner, 2026-10-26): an individual lists his car as an OWNER LISTING — one item, any
 * account, no business — instead of opening a business account of a trade of its own. The child had no account.
 *
 * The master row goes; what hangs from it by a cascade (option links, service configs, the fee rows, the parent pivot) goes
 * with it, and the two tables with no foreign key are cleared by hand. A child that DID have an account would be left alone
 * (an account is never orphaned silently): the migration then leaves it and says so.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = (int) DB::table('category_children_master')->where('name_ar', 'سيارة من المالك')->value('id');

        if ($id <= 0) {
            return;
        }

        if (DB::table('users')->where('category_child_id', $id)->exists()) {
            fwrite(STDERR, "«سيارة من المالك» still has accounts: not retired.\n");

            return;
        }

        DB::table('service_option_group_placements')->where('child_id', $id)->delete();

        // no foreign key on these two: the services switched on for the child, and its fee/config rows by child id
        foreach (['category_platform_services', 'category_service_configs'] as $table) {
            if (DB::getSchemaBuilder()->hasTable($table)) {
                DB::table($table)->where('child_id', $id)->delete();
            }
        }

        if (DB::getSchemaBuilder()->hasTable('booking_shape_children')) {
            DB::table('booking_shape_children')->where('child_id', $id)->delete();
        }

        DB::table('category_children_master')->where('id', $id)->delete();

        // the children are numbered in the alphabet with no gap: the row that went leaves a hole to close
        app(\Database\Seeders\DisplayOrderSeeder::class)->run();
    }

    public function down(): void
    {
        // a retired trade is not brought back by guesswork
    }
};
