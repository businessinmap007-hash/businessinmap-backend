<?php

namespace Database\Seeders;

use App\Services\Catalog\ChildOptionDecisions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Breaks «أقسام السوبر ماركت» into the five counters its own link data draws.
 *
 *     php artisan db:seed --class=GroceryAisleSplitSeeder
 *
 * See data/grocery_aisle_split.php for the five sets, how they were derived,
 * and what was deliberately left alone.
 *
 * Same promise as MenuBandSplitSeeder, which this follows exactly: only
 * `options.group_id` moves. No option is deleted and no `category_child_option`
 * row is touched by the split itself, so a supermarket that carried 27 aisles
 * still carries 27 — under five titles instead of one — and a fishmonger sees
 * only the counter he runs.
 *
 * The one exception is stated out loud in the data file and handled here in
 * `moveFishmongersToTheirOwnAisle()`: nine shops were reaching a RESTAURANT
 * heading, «مأكولات بحرية» in «بنود المنيو», to say they sell fish. They get an
 * aisle option of their own and give the menu row back to the restaurants.
 *
 * Idempotent. A second run moves nothing and says so.
 */
class GroceryAisleSplitSeeder extends Seeder
{
    public function run(): void
    {
        $data = require __DIR__ . '/data/grocery_aisle_split.php';

        DB::transaction(function () use ($data) {
            $sourceId = (int) DB::table('option_groups')->where('name_ar', $data['source_group'])->value('id');

            if ($sourceId <= 0) {
                $this->command?->warn("  ! «{$data['source_group']}» غير موجودة — لا شيء ليُقسم.");

                return;
            }

            $this->command?->info('Grocery aisle split:');

            $this->applyRenames($data['renames'] ?? []);

            $moved = 0;

            foreach ($data['groups'] as $nameAr => $group) {
                $moved += $this->moveInto($nameAr, $group, $sourceId);
            }

            $created = $this->createNewOptions($data['new_options'] ?? []);

            $this->finishRangeMove($data['finish_range_move'] ?? []);

            $this->reportLeftovers($sourceId);
            $this->retireEmptySource($sourceId, $data['source_group']);

            $this->command?->line("  - خيارات نُقلت : {$moved}");
            $this->command?->line("  - خيارات أُنشئت : {$created}");
        });
    }

    /**
     * Take off the aisle rows the owner's own move left behind.
     *
     * He moves a market child onto «أصناف المنتجات الغذائية» and withdraws the
     * aisle rows the ranges repeat, one child at a time through the card. Where
     * a row was missed, this finishes it — as a WITHDRAWAL, so the broad
     * seeders that grant whole groups do not hand it back on the next run.
     *
     * Skipped if a merchant has ticked the word himself: `option_user` outranks
     * any map, here as everywhere in this file.
     *
     * @param  array<int,array{child:string,group:string,option:string}>  $rows
     */
    private function finishRangeMove(array $rows): void
    {
        $decisions = app(ChildOptionDecisions::class);

        foreach ($rows as $row) {
            $childId = (int) DB::table('category_children_master')->where('name_ar', $row['child'])->value('id');

            $optionId = (int) DB::table('options as o')
                ->join('option_groups as g', 'g.id', '=', 'o.group_id')
                ->where('g.name_ar', $row['group'])->where('o.name_ar', $row['option'])
                ->value('o.id');

            if ($childId <= 0 || $optionId <= 0) {
                continue;
            }

            $chosen = DB::table('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->where('u.category_child_id', $childId)->where('ou.option_id', $optionId)
                ->exists();

            if ($chosen) {
                $this->command?->line("  · «{$row['child']}» اختاره تاجر — «{$row['option']}» بقي.");

                continue;
            }

            $decisions->record($childId, ChildOptionDecisions::ALL_ROOTS, [$optionId], 'baseline');

            $gone = DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $optionId)->delete();

            if ($gone > 0) {
                $this->command?->line("  - «{$row['child']}» ← «{$row['option']}» من «{$row['group']}» سُحب");
            }
        }
    }

    /**
     * Rename a group this file has already created, before anything looks one
     * up by name.
     *
     * Renaming in the data file alone is not enough: `group()` finds by name and
     * creates when missing, so the next run would build a fresh empty group,
     * find nothing left in the source to move into it, and strand the options in
     * a group nothing declares. Runs first for the same reason.
     *
     * @param  array<string,string>  $renames
     */
    private function applyRenames(array $renames): void
    {
        foreach ($renames as $from => $to) {
            $fromId = (int) DB::table('option_groups')->where('name_ar', $from)->value('id');

            if ($fromId <= 0) {
                continue;
            }

            // Both names present means somebody created the new one by hand.
            // Merging them is a decision, not a rename — say so and stop.
            if (DB::table('option_groups')->where('name_ar', $to)->exists()) {
                $this->command?->warn("  ! «{$from}» و«{$to}» موجودتان معًا — الدمج قرار وليس إعادة تسمية.");

                continue;
            }

            DB::table('option_groups')->where('id', $fromId)
                ->update(['name_ar' => $to, 'updated_at' => now()]);

            $this->command?->line("  - «{$from}» ← «{$to}»");
        }
    }

    /**
     * @param  array<string,mixed>  $group
     */
    private function moveInto(string $nameAr, array $group, int $sourceId): int
    {
        $groupId = $this->group($nameAr, $group['name_en'], $group['reorder']);

        // Only options standing in the SOURCE group move. An option that has
        // since been filed somewhere else on purpose is left where it is —
        // this seeder splits one list, it does not collect a list from the
        // whole table by name.
        $moved = DB::table('options')
            ->where('group_id', $sourceId)
            ->whereIn('name_ar', $group['options'])
            ->update(['group_id' => $groupId]);

        if ($moved > 0) {
            $this->command?->line("  - «{$nameAr}» ← {$moved}");
        }

        return $moved;
    }

    /**
     * A `line` in every case, and that is not a default.
     *
     * A grocery child sells off a market list: مني ماركت and هايبر ماركت carry
     * `menu` and no `retail` at all, so the aisle heading IS the priced row for
     * them, exactly as «مشويات» is for a restaurant. The catalog branch does
     * the same job for سوبر ماركت, which carries both — that is the two axes
     * agreeing, not competing.
     */
    private function group(string $nameAr, string $nameEn, int $reorder): int
    {
        $id = (int) DB::table('option_groups')->where('name_ar', $nameAr)->value('id');

        if ($id > 0) {
            // Keep the English in step with a rename. Only the Arabic is keyed,
            // so without this «بنود المخبوزات والحلويات» keeps describing itself
            // as an Aisle to every English reader of the admin.
            DB::table('option_groups')->where('id', $id)->where('name_en', '!=', $nameEn)
                ->update(['name_en' => $nameEn, 'updated_at' => now()]);

            return $id;
        }

        return (int) DB::table('option_groups')->insertGetId([
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'price_role' => 'line',
            'reorder' => $reorder,
            'is_active' => 1,
        ]);
    }

    /** @param array<string,mixed> $newOptions */
    private function createNewOptions(array $newOptions): int
    {
        $created = 0;

        foreach ($newOptions as $nameAr => $spec) {
            $groupId = (int) DB::table('option_groups')->where('name_ar', $spec['group'])->value('id');

            if ($groupId <= 0) {
                $this->command?->warn("  ! «{$spec['group']}» غير موجودة — «{$nameAr}» لم يُنشأ.");

                continue;
            }

            $optionId = (int) DB::table('options')->where('name_ar', $nameAr)->value('id');

            if ($optionId <= 0) {
                // `options.name_en` is UNIQUE across the whole table, and the
                // table has no `is_active` — an option lives or dies by its
                // group.
                $optionId = (int) DB::table('options')->insertGetId([
                    'name_ar' => $nameAr,
                    'name_en' => $spec['name_en'],
                    'group_id' => $groupId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $created++;
            }

            if (isset($spec['move_from'])) {
                $this->moveFishmongersToTheirOwnAisle($optionId, $spec['move_from']);
            }

            if (isset($spec['grant_to'])) {
                $this->grantTo($optionId, $nameAr, $spec['grant_to']);
            }
        }

        return $created;
    }

    /**
     * Hand a new aisle option to the children named for it.
     *
     * A SHARED row (`category_id = 0`), because a trade says the same thing
     * under every root it stands beneath — and a withdrawal is honoured, so if
     * the owner has already taken this word off the child by hand it stays off.
     *
     * @param  array<int,string>  $childNames
     */
    private function grantTo(int $optionId, string $optionNameAr, array $childNames): void
    {
        $withdrawn = app(ChildOptionDecisions::class)->blockedByChild();

        $granted = 0;

        $childIds = DB::table('category_children_master')
            ->whereIn('name_ar', $childNames)
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.child_id', 'category_children_master.id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        foreach ($childIds as $childId) {
            if (isset($withdrawn[$childId][$optionId])) {
                continue;
            }

            $granted += DB::table('category_child_option')->insertOrIgnore([
                'child_id' => $childId,
                'category_id' => 0,
                'option_id' => $optionId,
                'reorder' => 0,
            ]);
        }

        if ($granted > 0) {
            $this->command?->line("  - «{$optionNameAr}» ← منح {$granted}");
        }
    }

    /**
     * Hand the new aisle option to the shops, and take the restaurant heading
     * back off them.
     *
     * The order matters and it is the safe one: GRANT first, then revoke. A
     * shop is never without a way to say it sells fish, not even for the width
     * of this transaction.
     *
     * A merchant who has already ticked «مأكولات بحرية» himself keeps it —
     * `option_user` outranks any map, here as everywhere. So does a pin: if the
     * owner put that row on this child by hand, it stays.
     *
     * @param  array<string,mixed>  $spec
     */
    private function moveFishmongersToTheirOwnAisle(int $optionId, array $spec): void
    {
        $menuOptionId = (int) DB::table('options as o')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('g.name_ar', $spec['group'])
            ->where('o.name_ar', $spec['option'])
            ->value('o.id');

        if ($menuOptionId <= 0) {
            $this->command?->warn("  ! «{$spec['option']}» غير موجود في «{$spec['group']}» — لم يُنقل أحد.");

            return;
        }

        $childIds = DB::table('category_children_master')
            ->whereIn('name_ar', $spec['children'])
            ->whereExists(fn ($q) => $q->from('category_parent_child as pc')->whereColumn('pc.child_id', 'category_children_master.id'))
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        $pinned = app(ChildOptionDecisions::class)->pinnedByChild();

        $granted = $revoked = 0;

        foreach ($childIds as $childId) {
            $held = DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $menuOptionId)
                ->get(['category_id']);

            if ($held->isEmpty()) {
                continue;
            }

            foreach ($held as $row) {
                $granted += DB::table('category_child_option')->insertOrIgnore([
                    'child_id' => $childId,
                    'category_id' => (int) $row->category_id,
                    'option_id' => $optionId,
                    'reorder' => 0,
                ]);
            }

            if (isset($pinned[$childId][$menuOptionId])) {
                $this->command?->line("  · #{$childId} ثبّته المالك بيده — «{$spec['option']}» بقي.");

                continue;
            }

            $chosen = DB::table('option_user as ou')
                ->join('users as u', 'u.id', '=', 'ou.user_id')
                ->where('u.category_child_id', $childId)
                ->where('ou.option_id', $menuOptionId)
                ->exists();

            if ($chosen) {
                $this->command?->line("  · #{$childId} اختاره تاجر — «{$spec['option']}» بقي.");

                continue;
            }

            $revoked += DB::table('category_child_option')
                ->where('child_id', $childId)->where('option_id', $menuOptionId)
                ->delete();
        }

        $this->command?->line("  - «{$spec['option']}» ← منح {$granted} / سحب {$revoked}");
    }

    /**
     * The emptied parent is switched OFF, not deleted.
     *
     * Deleting it would lose the record of where the five came from, and
     * nothing in this taxonomy is deleted. But leaving it active makes it a
     * live group offering nothing — a heading a merchant can open and find
     * blank — which is what `TaxonomyRedistributionTest` forbids and rightly.
     * Inactive-and-empty is a tombstone; active-and-empty is a bug.
     */
    private function retireEmptySource(int $sourceId, string $nameAr): void
    {
        if (DB::table('options')->where('group_id', $sourceId)->exists()) {
            return;
        }

        $updated = DB::table('option_groups')
            ->where('id', $sourceId)->where('is_active', 1)
            ->update(['is_active' => 0, 'updated_at' => now()]);

        if ($updated > 0) {
            $this->command?->line("  - «{$nameAr}» فرغت وأُوقفت — تبقى سجلًّا لما خرج منها.");
        }
    }

    /**
     * All 27 leave, so the source should be empty. Anything still standing in
     * it is an option the five sets forgot, and the loudest place to find that
     * out is here rather than on the screen a merchant is looking at.
     */
    private function reportLeftovers(int $sourceId): void
    {
        $left = DB::table('options')->where('group_id', $sourceId)->pluck('name_ar');

        if ($left->isEmpty()) {
            return;
        }

        $this->command?->warn('  ! بقي في المصدر بلا مجموعة جديدة : ' . $left->implode('، '));
    }
}
