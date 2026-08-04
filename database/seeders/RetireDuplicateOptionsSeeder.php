<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Retires an option that a better-named twin supersedes, WITHOUT deleting it.
 *
 * `options` has no is_active column, so the group is the only retirement
 * boundary the schema offers (the same reason «تخصصات استشارية» was closed by
 * deactivating the group). This parks superseded options in a deactivated
 * «خيارات متقاعدة» group: they vanish from every picker, their links move to
 * the survivor so nothing a business selected is lost, and restoring one is a
 * single group_id update.
 *
 *   php artisan db:seed --class=RetireDuplicateOptionsSeeder
 *
 * First use (owner call 2026-08-02): the three subjects whose refined twins the
 * owner added by hand — جيولوجيا, حاسب آلي, علوم متكاملة. Those three have
 * since been deleted outright (2026-08-04), so the map is empty; see it below
 * for what parking turned out to cost. Add to the map for future duplicates;
 * idempotent and re-runnable.
 */
class RetireDuplicateOptionsSeeder extends Seeder
{
    private const RETIRED_GROUP = ['name_ar' => 'خيارات متقاعدة', 'name_en' => 'Retired Options'];

    /**
     * [group holding both, superseded name, surviving name]
     *
     * Empty since 2026-08-04: the first three — جيولوجيا، حاسب آلي، علوم
     * متكاملة — were parked here, then the «خيارات متقاعدة» group was deleted
     * from the admin panel (which set their group_id NULL, `options.group_id`
     * being ON DELETE SET NULL), and the owner then had the groupless rows
     * swept for good by OrphanOptionsCleanupSeeder. They are also gone from
     * TutoringCenterPoolsSeeder, so there is no superseded twin left to park.
     *
     * The mechanism stays for the next duplicate: add a row and re-run.
     */
    private const DUPLICATES = [];

    public function run(): void
    {
        if (self::DUPLICATES === []) {
            $this->command?->info('No duplicate options to retire.');

            return;
        }

        DB::transaction(function () {
            // Resolved only once something is actually being parked — creating
            // it up front left an empty group behind on every no-op run, and an
            // empty group is a dead entry in every picker.
            $retiredGroupId = $this->retiredGroupId();

            $retired = 0;
            $moved = 0;

            foreach (self::DUPLICATES as [$groupName, $supersededName, $survivorName]) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $groupName)->value('id');

                $superseded = DB::table('options')
                    ->where('group_id', $groupId)->where('name_ar', $supersededName)->value('id');
                $survivor = DB::table('options')
                    ->where('group_id', $groupId)->where('name_ar', $survivorName)->value('id');

                if (! $superseded || ! $survivor) {
                    continue; // already retired, or the pair no longer exists
                }

                // nothing a child or a business chose may be lost
                foreach (DB::table('category_child_option')->where('option_id', $superseded)->get() as $link) {
                    DB::table('category_child_option')->updateOrInsert(
                        ['child_id' => $link->child_id, 'option_id' => (int) $survivor],
                        ['reorder' => $link->reorder]
                    );
                    DB::table('category_child_option')->where('id', $link->id)->delete();
                    $moved++;
                }

                foreach (DB::table('option_user')->where('option_id', $superseded)->get() as $link) {
                    DB::table('option_user')->updateOrInsert(
                        ['user_id' => $link->user_id, 'option_id' => (int) $survivor], []
                    );
                    DB::table('option_user')->where('id', $link->id)->delete();
                    $moved++;
                }

                DB::table('options')->where('id', $superseded)->update(['group_id' => (int) $retiredGroupId]);
                $retired++;
            }

            $this->command?->info("Duplicate options retired: {$retired} (links re-pointed: {$moved}) → «{$this->groupLabel()}» #{$retiredGroupId}");
        });
    }

    private function groupLabel(): string
    {
        return self::RETIRED_GROUP['name_ar'];
    }

    private function retiredGroupId(): int
    {
        return (int) (DB::table('option_groups')->where('name_ar', self::RETIRED_GROUP['name_ar'])->value('id')
            ?: DB::table('option_groups')->insertGetId(self::RETIRED_GROUP + [
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 0,
            ]));
    }
}
