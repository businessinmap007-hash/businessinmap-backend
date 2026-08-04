<?php

namespace Database\Seeders;

use App\Models\OptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Two more furniture styles.
 *
 *   php artisan db:seed --class=FurnitureStyleOptionsSeeder
 *
 * «طراز الأثاث» held أنتيكات، كلاسيك، مودرن. A showroom selling ultra-modern
 * had to write it into the item's free-text name, where no filter and no
 * heading could reach it — the same hole «سوبر لوكس» left on property listings
 * before PropertyModifierOptionsSeeder.
 *
 * Modifiers, by the price test: nobody buys «ألترا مودرن», but an ultra-modern
 * bedroom costs more than a classic one. The line stays «غرفة نوم».
 *
 * Linked to every child that already carries a style, SHARED (category_id = 0)
 * so it follows the child under every root — a factory, a showroom and a
 * workshop all describe their work the same way. Idempotent.
 */
class FurnitureStyleOptionsSeeder extends Seeder
{
    private const GROUP = 'طراز الأثاث';

    private const OPTIONS = [
        ['ألترا مودرن', 'Ultra Modern'],
        ['ألترا كلاسيك', 'Ultra Classic'],
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $group = DB::table('option_groups')->where('name_ar', self::GROUP)->first(['id', 'price_role']);

            if (! $group) {
                $this->command?->warn('  ! مجموعة «' . self::GROUP . '» غير موجودة — لم يُضف شيء.');

                return;
            }

            $groupId = (int) $group->id;
            $children = $this->childrenCarryingTheGroup($groupId);

            if ($children->isEmpty()) {
                $this->command?->warn('  ! لا يوجد ابن يحمل طرازات الأثاث — لم يُربط شيء.');

                return;
            }

            $created = $linked = 0;
            $order = (int) DB::table('category_child_option as co')
                ->join('options as o', 'o.id', '=', 'co.option_id')
                ->where('o.group_id', $groupId)
                ->max('co.reorder');

            foreach (self::OPTIONS as $i => [$ar, $en]) {
                $optionId = $this->option($ar, $en, $groupId, $created);
                $linked += $this->link($optionId, $children, $order + $i + 1);
            }

            // The group must stay a modifier — a style qualifies a price, it is
            // never the thing bought.
            if ((string) $group->price_role !== OptionGroup::ROLE_MODIFIER) {
                DB::table('option_groups')->where('id', $groupId)
                    ->update(['price_role' => OptionGroup::ROLE_MODIFIER, 'updated_at' => now()]);
            }

            $this->command?->info('Furniture styles:');
            $this->command?->line("  - خيارات جديدة : {$created}");
            $this->command?->line("  - روابط أُضيفت : {$linked}");
            $this->command?->line('  - الأبناء : ' . $children->implode('، '));
        });
    }

    /** @return \Illuminate\Support\Collection<int,string> child id => name */
    private function childrenCarryingTheGroup(int $groupId)
    {
        return DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('category_children_master as ch', 'ch.id', '=', 'co.child_id')
            ->where('o.group_id', $groupId)
            ->distinct()
            ->pluck('ch.name_ar', 'ch.id');
    }

    /**
     * Matched on the globally-unique name_en first. A found option is left in
     * whatever group it already sits in: a seeder says what must EXIST, not
     * where the owner must keep it.
     */
    private function option(string $ar, string $en, int $groupId, int &$created): int
    {
        $id = DB::table('options')->where('name_en', $en)->value('id')
            ?: DB::table('options')->where('name_ar', $ar)->value('id');

        if ($id) {
            return (int) $id;
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

    private function link(int $optionId, $children, int $order): int
    {
        $rows = [];

        foreach ($children->keys() as $childId) {
            $rows[] = [
                'child_id' => (int) $childId,
                'category_id' => 0,   // shared: follows the child under every root
                'option_id' => $optionId,
                'reorder' => $order,
            ];
        }

        return DB::table('category_child_option')->insertOrIgnore($rows);
    }
}
