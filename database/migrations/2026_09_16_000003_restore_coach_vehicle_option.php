<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * options #220 («كوتش» / Coach) is declared by id in
 * `database/seeders/data/vehicle_option_groups.php` (group «مركبات النقل
 * والركاب», #60) and in `child_option_scopes.php`, but the row itself
 * was deleted from `options` at some point before 2026-09-15 — leaving a
 * hand-curated withdrawal (children 169, 85, 278, dated 2026-08-14) pointing
 * at nothing, and every OTHER child that legitimately carries this option
 * (e.g. #244 ونش إنقاذ) unable to have it granted: VehicleOptionGroupsSeeder's
 * insert fails on a foreign key violation the moment it tries.
 *
 * Restored with its original id so every existing reference (the withdrawal
 * decisions, the seeder's declared option list) resolves again, same as the
 * "التسليم والاستلام" group recovery the day before.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('options')->where('id', 220)->exists()) {
            return;
        }

        DB::table('options')->insert([
            'id' => 220,
            'group_id' => 60,
            'name_ar' => 'كوتش',
            'name_en' => 'Coach',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('options')->where('id', 220)->delete();
    }
};
