<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Breaks «بنود المنيو» into the four vocabularies it always was.
 *
 *     php artisan db:seed --class=MenuBandSplitSeeder
 *
 * See data/menu_band_split.php for the four sets and how they were derived —
 * from which CHILDREN carry each band, not from the names.
 *
 * Only the group changes. No option is created, renamed or deleted, and no
 * `category_child_option` row is touched: a supermarket that carried «مخبوزات»
 * still carries the same option id, now filed under «أقسام السوبر ماركت». That
 * is what makes this safe to run against live data — the merchant's menu keeps
 * every heading it had, and only the screen's grouping changes.
 *
 * Idempotent: a second run reports zero moved.
 */
class MenuBandSplitSeeder extends Seeder
{
    private const SOURCE = 'بنود المنيو';

    public function run(): void
    {
        $data = require __DIR__ . '/data/menu_band_split.php';

        DB::transaction(function () use ($data) {
            $sourceId = (int) DB::table('option_groups')->where('name_ar', self::SOURCE)->value('id');

            if ($sourceId <= 0) {
                $this->command?->warn('  ! «' . self::SOURCE . '» غير موجودة — لا شيء ليُقسم.');

                return;
            }

            $this->command?->info('Menu band split:');

            foreach ($data['groups'] as $group) {
                $this->moveInto($group, $sourceId);
            }

            $this->reclaim($data['kept'], $sourceId);
            $this->reportLeftovers($sourceId, $data['kept']);
        });
    }

    /** @param array<string,mixed> $group */
    private function moveInto(array $group, int $sourceId): void
    {
        $groupId = (int) DB::table('option_groups')->where('name_ar', $group['name_ar'])->value('id');

        if ($groupId <= 0) {
            $groupId = (int) DB::table('option_groups')->insertGetId([
                'name_ar' => $group['name_ar'],
                'name_en' => $group['name_en'],
                'price_role' => $group['price_role'],
                'reorder' => 1 + (int) DB::table('option_groups')->max('reorder'),
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Scoped to the SOURCE group. A band already moved keeps its new home,
        // and a same-named option living in some other group is none of this
        // seeder's business — «حلويات» is a restaurant heading here and a
        // «حلويات وشوكولاتة» aisle there, and only ids can tell them apart.
        $moved = DB::table('options')
            ->where('group_id', $sourceId)
            ->whereIn('name_ar', $group['bands'])
            ->update(['group_id' => $groupId, 'updated_at' => now()]);

        $total = DB::table('options')->where('group_id', $groupId)->count();

        $this->command?->line("  - «{$group['name_ar']}» ({$group['price_role']}) : نُقل {$moved} · الإجمالي {$total}");

        $missing = array_diff(
            $group['bands'],
            DB::table('options')->where('group_id', $groupId)->pluck('name_ar')->all()
        );

        foreach ($missing as $band) {
            $this->command?->warn("      ! البند «{$band}» غير موجود.");
        }
    }

    /**
     * Pull the bands named in `kept` back into «بنود المنيو».
     *
     * The split only ever pushed. That was enough while every ruling moved a
     * band OUT, and it stopped being enough the first time one had to come back:
     * «فطائر» left with the aisles in this split and left again with the bakery
     * counter in the grocery one, so removing its name from both lists would
     * have changed nothing at all — neither seeder touches an option that is not
     * standing in its own source.
     *
     * Scoped to the groups the two splits created, and no further. Reclaiming by
     * name from the whole table would be the mistake `moveInto()` names three
     * lines up: «حلويات» is a restaurant heading here and something else
     * somewhere, and only ids can tell them apart. The family is bounded, so an
     * option filed elsewhere on purpose is safe from this.
     *
     * Only `options.group_id` moves — the promise in this file's docblock holds.
     * A child that carried the band still carries the same option id.
     *
     * @param  array<int,string>  $kept
     */
    private function reclaim(array $kept, int $sourceId): void
    {
        $family = $this->familyOf($sourceId);

        if ($family === []) {
            return;
        }

        $returning = DB::table('options')
            ->whereIn('group_id', $family)
            ->whereIn('name_ar', $kept)
            ->pluck('name_ar');

        if ($returning->isEmpty()) {
            return;
        }

        DB::table('options')
            ->whereIn('group_id', $family)
            ->whereIn('name_ar', $kept)
            ->update(['group_id' => $sourceId, 'updated_at' => now()]);

        $this->command?->line('  - عادت إلى «' . self::SOURCE . '» : ' . $returning->implode('، '));
    }

    /**
     * Every group either split produced, plus the one the second split emptied.
     *
     * Read from the data files rather than listed, for the reason the grocery
     * file's `renames` block exists: these groups are found by NAME, and a name
     * that changes in one place and not the other strands options in a group
     * nothing declares.
     *
     * @return array<int,int>
     */
    private function familyOf(int $sourceId): array
    {
        $grocery = require __DIR__ . '/data/grocery_aisle_split.php';

        $names = array_merge(
            array_column((require __DIR__ . '/data/menu_band_split.php')['groups'], 'name_ar'),
            array_keys($grocery['groups']),
            [$grocery['source_group']],
            array_values($grocery['renames'] ?? []),
        );

        return DB::table('option_groups')
            ->whereIn('name_ar', $names)
            ->where('id', '!=', $sourceId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @param array<int,string> $kept */
    private function reportLeftovers(int $sourceId, array $kept): void
    {
        $left = DB::table('options')->where('group_id', $sourceId)->pluck('name_ar')->all();

        $this->command?->line('  - «' . self::SOURCE . '» بقي فيها : ' . count($left));

        $unexpected = array_diff($left, $kept);

        foreach ($unexpected as $band) {
            $this->command?->warn("      ! «{$band}» بقي في المجموعة الأصلية ولم يُذكر في «kept».");
        }
    }
}
