<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Breaks up the option groups that were still asking more than one question.
 *
 *   php artisan db:seed --class=OptionGroupSplitSeeder
 *
 * After the commerce group (24 → eight) and the vehicle group (68 → three), a
 * sweep of every remaining group found four more mixes. The long groups that are
 * NOT mixes stay whole — «ماركات السيارات» (43), «تخصصات طبية» (41), «الأنشطة
 * الرياضية» (45), «المواد الدراسية» (38), «التحاليل الطبية» (28) — each asks
 * exactly one question, and cutting it up only adds headings. Three of those
 * were briefly cut anyway and are folded back by `merges`; see the data file for
 * what the screen looked like while they were split.
 *
 * The four that were mixes:
 *   مرافق الإقامة      → facilities + إطلالة الوحدة + نظام الوجبات
 *   عقارات وممتلكات    → property types + نوع التعامل, with كاش/تقسيط
 *                        folded into the payment group that already asks that
 *   أثاث وتشطيب منزلي → pieces + طراز الأثاث
 *   مجالات التدريب     → fields + اللغات
 *
 * `category_child_option` is untouched by design: a link points at an OPTION, so
 * an option carries its links into its new group and no child loses an answer.
 *
 * Idempotent.
 */
class OptionGroupSplitSeeder extends Seeder
{
    public function run(): void
    {
        $map = require database_path('seeders/data/option_group_splits.php');

        DB::transaction(function () use ($map) {
            $created = $moved = $folded = 0;

            // Merges run FIRST so a group can be folded back and, if the map
            // ever asks for it again, re-split in the same pass.
            [$mergedOptions, $droppedGroups] = $this->mergeBack($map['merges'] ?? []);

            foreach ($map['splits'] as $sourceName => $plan) {
                $sourceId = (int) DB::table('option_groups')->where('name_ar', $sourceName)->value('id');

                if (! $sourceId) {
                    $this->command?->warn("  ! مجموعة «{$sourceName}» غير موجودة — تُركت.");

                    continue;
                }

                foreach ($plan['new'] ?? [] as $name => $group) {
                    [$groupId, $isNew] = $this->ensureGroup($name, $group);

                    $created += $isNew ? 1 : 0;
                    $moved += $this->refile($group['options'], $groupId);
                }

                foreach ($plan['into_existing'] ?? [] as $name => $optionIds) {
                    $targetId = (int) DB::table('option_groups')->where('name_ar', $name)->value('id');

                    if (! $targetId) {
                        $this->command?->warn("  ! مجموعة «{$name}» غير موجودة — لم يُنقل إليها شيء.");

                        continue;
                    }

                    $folded += $this->refile($optionIds, $targetId);
                }

                $left = DB::table('options')->where('group_id', $sourceId)->count();
                $this->command?->line("      «{$sourceName}» بقي فيها {$left} خيارًا");
            }

            $this->command?->info('Option group splits:');
            $this->command?->line("  - خيارات أُعيدت لمجموعتها الأم : {$mergedOptions}");
            $this->command?->line("  - مجموعات فرعية أُزيلت بعد إفراغها : {$droppedGroups}");
            $this->command?->line("  - مجموعات جديدة : {$created}");
            $this->command?->line("  - خيارات نُقلت إليها : {$moved}");
            $this->command?->line("  - خيارات ضُمّت لمجموعة قائمة : {$folded}");
            $this->command?->line('  - روابط الأبناء : لم تتغيّر (' . DB::table('category_child_option')->count() . ')');
        });
    }

    /**
     * Fold a family back into the group it came from and remove the now-empty
     * family. Links are untouched, same as when splitting — they point at the
     * OPTION, so the option carries them home.
     *
     * @param  array<string,string>  $merges  family name => parent name
     * @return array{0:int,1:int}
     */
    private function mergeBack(array $merges): array
    {
        $options = $groups = 0;

        foreach ($merges as $family => $parent) {
            $familyId = DB::table('option_groups')->where('name_ar', $family)->value('id');

            if (! $familyId) {
                continue; // already folded back
            }

            $parentId = DB::table('option_groups')->where('name_ar', $parent)->value('id');

            if (! $parentId) {
                $this->command?->warn("  ! مجموعة «{$parent}» غير موجودة — تُركت «{$family}» كما هي.");

                continue;
            }

            $options += DB::table('options')
                ->where('group_id', $familyId)
                ->update(['group_id' => (int) $parentId]);

            // only ever delete a group this pass just emptied
            if (DB::table('options')->where('group_id', $familyId)->doesntExist()) {
                DB::table('option_groups')->where('id', $familyId)->delete();
                $groups++;
            }
        }

        return [$options, $groups];
    }

    /** @return array{0:int,1:bool} */
    private function ensureGroup(string $name, array $group): array
    {
        $id = DB::table('option_groups')->where('name_ar', $name)->value('id');

        if ($id) {
            return [(int) $id, false];
        }

        $id = DB::table('option_groups')->insertGetId([
            'name_ar' => $name,
            'name_en' => $group['name_en'],
            'reorder' => $group['reorder'],
            'is_active' => 1,
        ]);

        return [(int) $id, true];
    }

    private function refile(array $optionIds, int $groupId): int
    {
        return DB::table('options')
            ->whereIn('id', $optionIds)
            ->where(fn ($q) => $q->where('group_id', '!=', $groupId)->orWhereNull('group_id'))
            ->update(['group_id' => $groupId]);
    }
}
