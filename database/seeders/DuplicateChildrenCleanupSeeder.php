<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Removes the four cases where one root offered the SAME child name twice, so a
 * merchant picking «حداد» under مهن وحرفيين faced two identical entries and the
 * accounts split between them at random.
 *
 *   php artisan db:seed --class=DuplicateChildrenCleanupSeeder
 *
 * In every case the copy being unlinked carries ZERO accounts under that root,
 * so nothing is stranded, and only the (root, child) pivot row goes — the
 * master row survives because three of the four are legitimately shared with
 * ANOTHER root (#31 حداد is also a workshops child, #43 قطع غيار also a
 * factories child, #7 أجهزة رياضية also a shops-online child). Only #237
 * ملابس جاهزة is pure redundancy with no other root and no accounts anywhere.
 *
 * Service configs for the unlinked pair are deactivated across EVERY service,
 * not just booking — BookingChildModesSeeder's orphan sweep only covers its own.
 */
class DuplicateChildrenCleanupSeeder extends Seeder
{
    /** [root slug, child id to unlink, child id that keeps the accounts, name] */
    private const UNLINK = [
        ['professions', 31, 259, 'حداد'],
        ['shops-online', 43, 44, 'قطع غيار سيارات'],
        ['exhibitions', 7, 24, 'أجهزة رياضية'],
        ['exhibitions', 237, 60, 'ملابس جاهزة'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $unlinked = 0;
            $configs = 0;

            foreach (self::UNLINK as [$rootSlug, $dropId, $keepId, $name]) {
                $rootId = (int) DB::table('categories')->where('slug', $rootSlug)->value('id');

                if (! $rootId) {
                    continue;
                }

                // never strand an account: refuse if this copy still holds any
                $held = DB::table('users')
                    ->where('category_child_id', $dropId)
                    ->where('category_id', $rootId)
                    ->count();

                if ($held > 0) {
                    $this->command?->warn("  ! «{$name}» #{$dropId} ما زال عليه {$held} حساب تحت {$rootSlug} — تُرك.");
                    continue;
                }

                $removed = DB::table('category_parent_child')
                    ->where('parent_id', $rootId)
                    ->where('child_id', $dropId)
                    ->delete();

                if (! $removed) {
                    continue; // already unlinked
                }

                $configs += DB::table('category_service_configs')
                    ->where('category_id', $rootId)
                    ->where('child_id', $dropId)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                DB::table('category_platform_services')
                    ->where('category_id', $rootId)
                    ->where('child_id', $dropId)
                    ->update(['is_active' => 0, 'updated_at' => now()]);

                $unlinked++;
                $this->command?->line("      {$rootSlug}: «{$name}» #{$dropId} فُصل — الحسابات تبقى على #{$keepId}");
            }

            $this->command?->info("Duplicate children cleaned: {$unlinked} unlinked, {$configs} configs deactivated.");
        });
    }
}
