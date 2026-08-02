<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Fixes the options left with no group at all (`group_id` NULL), found by the
 * duplicate-options audit. Two of the six are live problems; the other four are
 * already-retired rows whose parking group was deleted from the admin panel,
 * which set their group_id NULL — functionally still hidden, so they are left
 * alone rather than re-homed into a group the owner chose to remove.
 *
 *   php artisan db:seed --class=OrphanOptionsCleanupSeeder
 *
 * 1. «Kitchen draw» (#219) is a REAL option — five children link it (نجار
 *    موبيليا، مطابخ و دريسنج، مفروشات، نجف، نجف و تحف) — but it carries an
 *    English string in name_ar and belongs to no group, so no picker can show
 *    it. Renamed to «أدراج ووحدات مطبخ» and filed under أثاث وتشطيب منزلي.
 *
 * 2. «محافظات» (#81, "Cities") is a geographic concept, not a descriptor of a
 *    business. Location is handled by the platform's own location system, so
 *    its single link (to سيارات under معارض) is removed and the row is left
 *    groupless — retired the same way the others are, without deleting it.
 *
 * Idempotent; nothing is hard-deleted.
 */
class OrphanOptionsCleanupSeeder extends Seeder
{
    private const FURNITURE_GROUP = 'أثاث وتشطيب منزلي';

    public function run(): void
    {
        DB::transaction(function () {
            $fixed = $this->rehomeKitchenOption();
            $unlinked = $this->retireGeographyOption();

            $stillOrphan = DB::table('options as o')
                ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereNull('g.id')
                ->count();

            $liveOrphans = DB::table('options as o')
                ->leftJoin('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereNull('g.id')
                ->whereExists(function ($q) {
                    $q->from('category_child_option as co')->whereColumn('co.option_id', 'o.id');
                })
                ->count();

            $this->command?->info('Orphan options cleanup:');
            $this->command?->line("  - «Kitchen draw» re-homed : {$fixed}");
            $this->command?->line("  - «محافظات» links removed : {$unlinked}");
            $this->command?->line("  - groupless rows remaining : {$stillOrphan} (منها ما زال مستخدمًا: {$liveOrphans})");
        });
    }

    /** A real furniture attribute that lost its Arabic name and its group. */
    private function rehomeKitchenOption(): int
    {
        $option = DB::table('options')->where('id', 219)->first(['id', 'name_ar', 'group_id']);

        if (! $option || $option->group_id) {
            return 0; // absent, or already re-homed
        }

        $groupId = (int) DB::table('option_groups')->where('name_ar', self::FURNITURE_GROUP)->value('id');

        if (! $groupId) {
            $this->command?->warn('  ! مجموعة الأثاث غير موجودة — تُرك #219.');

            return 0;
        }

        DB::table('options')->where('id', 219)->update([
            'group_id' => $groupId,
            'name_ar' => 'أدراج ووحدات مطبخ',
            'name_en' => 'Kitchen Drawers & Units',
        ]);

        return 1;
    }

    /** Geography is not a business descriptor; the location system owns it. */
    private function retireGeographyOption(): int
    {
        $option = DB::table('options')->where('id', 81)->first(['id', 'group_id']);

        if (! $option) {
            return 0;
        }

        return DB::table('category_child_option')->where('option_id', 81)->delete();
    }
}
