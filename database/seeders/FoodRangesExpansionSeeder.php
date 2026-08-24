<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * «قم بمراجعة فروع أصناف المنتجات الغذائية … وبعد اكتمال كل فروعها نلغيها
 *  ونضيف المجموعات إلى السوبر ماركت والهايبر والميني ماركت» — المالك، 2026-08-24.
 *
 *     php artisan db:seed --class=FoodRangesExpansionSeeder
 *
 * The whole map is in data/food_ranges_expansion.php, including why each shelf
 * became a list and why the parent is switched off rather than emptied. This
 * file is the four steps that apply it, in the one order they can run in:
 *
 *   1. rename    — before anything is looked up by name
 *   2. groups    — create the lists, upsert their rows
 *   3. links     — hand them to the children, through the withdrawal ledger
 *   4. retire    — switch the replaced shelf list off
 *
 * Idempotent: a second run creates no option, adds no link and reports nothing.
 */
class FoodRangesExpansionSeeder extends Seeder
{
    /** Every list this file writes is a priced line. */
    private const ROLE = 'line';

    public function run(): void
    {
        $map = require __DIR__ . '/data/food_ranges_expansion.php';

        DB::transaction(function () use ($map) {
            $renamed = $this->rename($map['rename_options'] ?? []);

            $created = 0;

            foreach (($map['groups'] ?? []) as $nameAr => $spec) {
                $groupId = $this->group($nameAr, $spec['name_en']);

                foreach ($spec['options'] as $ar => $en) {
                    $this->option($groupId, $ar, $en, $created);
                }
            }

            foreach (($map['extend'] ?? []) as $nameAr => $options) {
                $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

                if ($groupId <= 0) {
                    $this->command?->warn("  ! مجموعة «{$nameAr}» غير موجودة — تُخطّى.");

                    continue;
                }

                foreach ($options as $ar => $en) {
                    $this->option($groupId, $ar, $en, $created);
                }
            }

            [$linked, $refused] = $this->link($map['links'] ?? []);

            $retired = $this->retire($map['retire'] ?? []);

            $this->command?->info('Food ranges expansion:');
            $this->command?->line("  - خيارات أُنشئت : {$created}");
            $this->command?->line("  - خيارات أُعيد تسميتها : {$renamed}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line("  - روابط رفضها سجل السحب : {$refused}");
            $this->command?->line("  - مجموعات أُوقفت : {$retired}");
        });
    }

    /**
     * Rename a row inside its own group.
     *
     * Scoped to the group so it can never touch a same-named row somewhere
     * else, and skipped when the new name is already there — a rename that ran
     * once has nothing to do the second time, and forcing it would collide with
     * the row it created.
     *
     * @param  array<string,array<string,array{0:string,1:string}>>  $renames
     */
    private function rename(array $renames): int
    {
        $n = 0;

        foreach ($renames as $groupName => $rows) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $groupName)->value('id');

            if ($groupId <= 0) {
                $this->command?->warn("  ! مجموعة «{$groupName}» غير موجودة — تسمية تُخطّى.");

                continue;
            }

            foreach ($rows as $from => [$toAr, $toEn]) {
                if (DB::table('options')->where('group_id', $groupId)->where('name_ar', $toAr)->exists()) {
                    continue;
                }

                $n += DB::table('options')
                    ->where('group_id', $groupId)
                    ->where('name_ar', $from)
                    ->update(['name_ar' => $toAr, 'name_en' => $toEn, 'updated_at' => now()]);
            }
        }

        return $n;
    }

    private function group(string $nameAr, string $nameEn): int
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $nameAr,
                'name_en' => $nameEn,
                // DisplayOrderSeeder renumbers every group alphabetically
                // within its role and runs last in the chain; this is only a
                // number that exists until it does.
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'price_role' => self::ROLE,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $groupId;
        }

        DB::table('option_groups')->where('id', $groupId)
            ->update(['is_active' => 1, 'price_role' => self::ROLE, 'updated_at' => now()]);

        return $groupId;
    }

    /**
     * `options.name_en` is unique platform-wide, so a name already taken by
     * another group would be written as «Molasses (2)» — a suffix that is not
     * a food. Both collisions this file found were resolved in the DATA
     * («لب سوري» → Roasted Sunflower Seeds, «عسل أسود» → Cane Molasses); this
     * refuses to write rather than invent a third.
     */
    private function option(int $groupId, string $ar, string $en, int &$created): int
    {
        $optionId = (int) DB::table('options')
            ->where('group_id', $groupId)->where('name_ar', $ar)->value('id');

        if ($optionId > 0) {
            return $optionId;
        }

        $taken = (int) DB::table('options')->where('name_en', $en)->value('id');

        if ($taken > 0) {
            $this->command?->warn("  ! «{$ar}»: الاسم الإنجليزي «{$en}» مأخوذ (#{$taken}) — لم يُكتب.");

            return 0;
        }

        $created++;

        return (int) DB::table('options')->insertGetId([
            'group_id' => $groupId,
            'name_ar' => $ar,
            'name_en' => $en,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Shared links (`category_id = 0`): a supermarket is a supermarket under
     * whichever root it is browsed from.
     *
     * The withdrawal ledger is consulted first, like every other option seeder.
     * The owner may take شيبسي off a mini-market tomorrow, and a seeder that
     * hands it back on the next run is the failure five others were taught out
     * of.
     *
     * @param  array<string,array{children:array<int,int>,groups:array<int,string>}>  $sets
     * @return array{0:int,1:int}
     */
    private function link(array $sets): array
    {
        $blocked = app(ChildOptionDecisions::class)->blockedByChild();

        $linked = 0;
        $refused = 0;

        foreach ($sets as $label => $set) {
            $optionIds = DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->whereIn('g.name_ar', $set['groups'])
                ->pluck('o.id');

            if ($optionIds->isEmpty()) {
                $this->command?->warn("  ! «{$label}»: لا خيارات تطابق مجموعاته.");

                continue;
            }

            foreach ($set['children'] as $childId) {
                $childId = (int) $childId;

                if (! DB::table('category_children_master')->where('id', $childId)->exists()) {
                    $this->command?->warn("  ! ابن #{$childId} غير موجود — يُتخطّى.");

                    continue;
                }

                foreach ($optionIds as $optionId) {
                    $optionId = (int) $optionId;

                    if (isset($blocked[$childId][$optionId])) {
                        $refused++;

                        continue;
                    }

                    $already = DB::table('category_child_option')
                        ->where('child_id', $childId)
                        ->where('option_id', $optionId)
                        ->where('category_id', 0)
                        ->exists();

                    if ($already) {
                        continue;
                    }

                    DB::table('category_child_option')->insert([
                        'child_id' => $childId,
                        'category_id' => 0,
                        'option_id' => $optionId,
                        'reorder' => 0,
                    ]);

                    $linked++;
                }
            }
        }

        return [$linked, $refused];
    }

    /**
     * Switched off, its rows left inside it, and its LINKS taken away.
     *
     * Unlike the produce and grocery splits there is nowhere for these twenty
     * to move to — a shelf name is not a variety — so the group keeps them as
     * the record of what the shelves were called.
     *
     * ⚠ The links cannot stay with them. `ChildOptionDecisionTest > a dissolved
     * row leaves no decision behind` states the rule the whole taxonomy runs
     * on: a retired row must reach no child and carry no decision, because the
     * reconciliation backstop reads `category_child_option` and would keep
     * restoring a row nothing shows any more. The two split seeders satisfy it
     * by emptying the group; this one has to satisfy it by hand.
     *
     * Safe to do here and nowhere else: on the day this ran the twenty carried
     * zero merchant ticks, zero prices and zero offerings. If any of that were
     * true the group would not be retired at all.
     *
     * @param  array<int,string>  $names
     */
    private function retire(array $names): int
    {
        $n = 0;

        foreach ($names as $nameAr) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

            if ($groupId <= 0) {
                $this->command?->warn("  ! مجموعة «{$nameAr}» غير موجودة — إيقاف يُتخطّى.");

                continue;
            }

            $optionIds = DB::table('options')->where('group_id', $groupId)->pluck('id');

            $ticked = DB::table('option_user')->whereIn('option_id', $optionIds)->count();

            if ($ticked > 0) {
                // A merchant answered with one of these words about himself.
                // Retiring it under him is not this file's decision to make.
                $this->command?->warn("  ! «{$nameAr}»: {$ticked} إجابة تاجر عليها — لم تُوقف.");

                continue;
            }

            $updated = DB::table('option_groups')
                ->where('id', $groupId)->where('is_active', 1)
                ->update(['is_active' => 0, 'updated_at' => now()]);

            $unlinked = DB::table('category_child_option')->whereIn('option_id', $optionIds)->delete();
            $undecided = DB::table('category_child_option_decisions')->whereIn('option_id', $optionIds)->delete();

            if ($updated > 0 || $unlinked > 0) {
                $this->command?->line(
                    "  - «{$nameAr}» أُوقفت — {$optionIds->count()} صفًّا تبقى بداخلها سجلًّا، "
                    . "و{$unlinked} رابطًا و{$undecided} قرارًا رُفعت."
                );
            }

            $n += $updated;
        }

        return $n;
    }
}
