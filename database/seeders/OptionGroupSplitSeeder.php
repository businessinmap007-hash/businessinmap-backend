<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
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

            [$dissolved, $granted] = $this->mergeRows($map['row_merges'] ?? [], $map['retired_group'] ?? null);

            $this->command?->info('Option group splits:');
            $this->command?->line("  - صفوف مركّبة حُلّت : {$dissolved}  (روابط منحت بدلًا منها: {$granted})");
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

    /**
     * Dissolve a compound row into the rows it was already made of.
     *
     * A group can ask two questions and a ROW can restate two answers standing
     * beside it: «شحن وتوصيل» is «شحن» and «توصيل طلبات» joined by a واو, and
     * all three were in one list.
     *
     * Dissolving is not deleting. Every link the compound held becomes a link
     * to each of its parts — at the SAME root scope, since a row written
     * against one root must not silently become every root's — and the child
     * keeps saying exactly what it was saying, in words the platform can also
     * count. A withdrawal blocks a grant here as it does everywhere: a child
     * whose owner took «توصيل طلبات» off by hand does not get it back through
     * the side door.
     *
     * A merchant's own tick moves with it, to every part. A PRICED row does
     * not: `line_option_id` points at one option and this merge has two
     * answers, so the merge is refused rather than guessed at. Nothing in this
     * group is priced today; the guard is for the day something is.
     *
     * @param  array<int,array<string,mixed>>  $merges
     * @param  array<string,string>|null  $retiredGroup
     * @return array{0:int,1:int}
     */
    private function mergeRows(array $merges, ?array $retiredGroup): array
    {
        if ($merges === []) {
            return [0, 0];
        }

        $blocked = app(ChildOptionDecisions::class)->blockedByChild();
        $dissolved = $granted = 0;

        foreach ($merges as $merge) {
            $from = (int) $merge['from'];
            $into = array_map('intval', $merge['into']);

            if (DB::table('options')->where('id', $from)->doesntExist()) {
                continue;
            }

            if (DB::table('business_service_prices')->where('line_option_id', $from)->exists()) {
                $this->command?->warn("  ! الخيار #{$from} مسعَّر — الدمج يحتاج قرارًا عن أي صفٍّ يرث السعر.");

                continue;
            }

            foreach (DB::table('category_child_option')->where('option_id', $from)->get(['child_id', 'category_id', 'reorder']) as $row) {
                foreach ($into as $keeper) {
                    if (isset($blocked[(int) $row->child_id][$keeper])) {
                        continue;
                    }

                    $granted += DB::table('category_child_option')->insertOrIgnore([
                        'child_id' => (int) $row->child_id,
                        'category_id' => (int) $row->category_id,
                        'option_id' => $keeper,
                        'reorder' => (int) $row->reorder,
                    ]);
                }
            }

            foreach (DB::table('option_user')->where('option_id', $from)->pluck('user_id') as $userId) {
                foreach ($into as $keeper) {
                    DB::table('option_user')->insertOrIgnore(['user_id' => (int) $userId, 'option_id' => $keeper]);
                }
            }

            DB::table('option_user')->where('option_id', $from)->delete();
            DB::table('category_child_option')->where('option_id', $from)->delete();

            if ($retiredGroup) {
                DB::table('options')->where('id', $from)
                    ->update(['group_id' => $this->retiredGroupId($retiredGroup), 'updated_at' => now()]);
            }

            $dissolved++;
        }

        return [$dissolved, $granted];
    }

    /**
     * The tombstone, and it is INACTIVE on purpose.
     *
     * Both the admin picker and MerchantOfferingVocabulary filter on
     * `option_groups.is_active`, so nothing offers a retired row while the row
     * itself keeps its id, its name and its history. `group_id = NULL` — what
     * VehicleOptionGroupsSeeder does — fails TaxonomyRedistributionTest, and
     * for the reason written there: a groupless option can be shown, edited or
     * restored by no screen at all, and it holds a `name_en` that is UNIQUE
     * platform-wide, so a dead row silently costs a live one its English name.
     *
     * @param  array<string,string>  $spec
     */
    private function retiredGroupId(array $spec): int
    {
        $id = DB::table('option_groups')->where('name_ar', $spec['name_ar'])->value('id');

        if ($id) {
            return (int) $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $spec['name_ar'],
            'name_en' => $spec['name_en'],
            'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
            'is_active' => 0,
            'price_role' => 'descriptive',
        ]);
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
