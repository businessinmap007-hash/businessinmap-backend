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
 * sweep of every remaining group found four more mixes and, importantly, several
 * long groups that are NOT mixes and are deliberately left alone — «ماركات
 * السيارات» (43), «تخصصات طبية» (41), «الأنشطة الرياضية» (45), «المواد
 * الدراسية» (38) and «التحاليل الطبية» (28) each ask exactly one question, and
 * cutting them up would only add headings.
 *
 * The four that were mixes:
 *   مرافق الإقامة      → facilities + إطلالة الوحدة + نظام الوجبات
 *   عقارات وممتلكات    → property types + نوع التعامل العقاري, with كاش/تقسيط
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
            $this->command?->line("  - مجموعات جديدة : {$created}");
            $this->command?->line("  - خيارات نُقلت إليها : {$moved}");
            $this->command?->line("  - خيارات ضُمّت لمجموعة قائمة : {$folded}");
            $this->command?->line('  - روابط الأبناء : لم تتغيّر (' . DB::table('category_child_option')->count() . ')');
        });
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
