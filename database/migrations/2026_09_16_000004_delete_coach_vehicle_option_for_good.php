<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * options #220 («كوتش» / Coach) was restored two migrations ago
 * (2026_09_16_000003) because `vehicle_option_groups.php` and
 * `child_option_scopes.php` still declared it and VehicleOptionGroupsSeeder
 * crashed on a foreign key violation trying to grant it. The owner then
 * asked for it to be deleted for good - now that both data files no longer
 * name it (removed the same day), this is durable: nothing will resurrect
 * it on a future seed.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('category_child_option_decisions')->where('option_id', 220)->delete();
        DB::table('option_user')->where('option_id', 220)->delete();
        DB::table('category_child_option')->where('option_id', 220)->delete();
        DB::table('options')->where('id', 220)->delete();
    }

    public function down(): void
    {
        // Irreversible on purpose - see 2026_09_16_000003 for how to restore
        // the option row itself if this is ever needed again; the withdrawn
        // decisions (children 169, 85, 278, dated 2026-08-14) cannot be
        // reconstructed from anything this migration kept.
    }
};
