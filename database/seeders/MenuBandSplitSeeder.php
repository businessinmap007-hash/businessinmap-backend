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
