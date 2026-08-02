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
 * owner added by hand — جيولوجيا, حاسب آلي, علوم متكاملة. Add to the map for
 * future duplicates; idempotent and re-runnable.
 */
class RetireDuplicateOptionsSeeder extends Seeder
{
    private const RETIRED_GROUP = ['name_ar' => 'خيارات متقاعدة', 'name_en' => 'Retired Options'];

    /** [group holding both, superseded name, surviving name] */
    private const DUPLICATES = [
        ['المواد الدراسية', 'جيولوجيا', 'جيولوجيا وعلوم بيئة'],
        ['المواد الدراسية', 'حاسب آلي', 'حاسب آلي وتكنولوجيا معلومات'],
        ['المواد الدراسية', 'علوم متكاملة', 'علوم'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $retiredGroupId = DB::table('option_groups')->where('name_ar', self::RETIRED_GROUP['name_ar'])->value('id')
                ?: DB::table('option_groups')->insertGetId(self::RETIRED_GROUP + [
                    'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                    'is_active' => 0,
                ]);

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
}
