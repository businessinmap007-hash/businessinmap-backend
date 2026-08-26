<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «مصانع» → «مواد غذائية» (child #109, root #23) sells wholesale into the
 * retail catalog — its buyers are other businesses (e.g. a supermarket
 * sourcing supply), not walk-in shoppers. A real meat-packing, dairy, or
 * produce-packing factory genuinely supplies fresh/raw categories wholesale,
 * so this child should carry the same fresh-category option groups
 * «سوبر ماركت» (child #272, root #17) already carries: meat, fish/seafood,
 * fruit, vegetables, dairy/cheese.
 *
 * Deliberately excludes two categories from the same source child, on the
 * SAME "wholesale factory, no retail fridge" logic, checked item-by-item:
 * «أنواع الدواجن والطيور» (poultry) — its options are live birds and
 * hatching eggs (بيض تفريخ، كتاكيت), livestock trade rather than food-factory
 * output — and «أصناف الحلويات والجاتوه», whose only member, «آيس كريم», is
 * a frozen dessert needing a retail freezer, distinct from the shelf-stable
 * packaged sweets this child already carries under «أنواع الحلويات المعبأة».
 *
 * See tests/Feature/FoodRangesExpansionTest::test_a_wholesale_food_factory_carries_fresh_produce_but_not_livestock_or_frozen_desserts
 * and the widened tests/Feature/FreshCounterVarietiesTest::carriers().
 */
return new class extends Migration
{
    private const SOURCE_CHILD_ID = 272;
    private const SOURCE_ROOT_ID = 17;
    private const TARGET_CHILD_ID = 109;

    private const WHOLESALE_FRESH_GROUPS = [
        'الفواكه',
        'الخضروات',
        'أنواع الأسماك والمأكولات البحرية',
        'أنواع اللحوم',
        'أنواع الألبان والأجبان',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('category_child_option') || ! Schema::hasTable('options') || ! Schema::hasTable('option_groups')) {
            return;
        }

        $rows = DB::table('category_child_option as co')
            ->join('options as o', 'o.id', '=', 'co.option_id')
            ->join('option_groups as g', 'g.id', '=', 'o.group_id')
            ->where('co.child_id', self::SOURCE_CHILD_ID)
            ->whereIn('co.category_id', [0, self::SOURCE_ROOT_ID])
            ->whereIn('g.name_ar', self::WHOLESALE_FRESH_GROUPS)
            ->select('co.option_id', 'co.reorder')
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $exists = DB::table('category_child_option')
                ->where('child_id', self::TARGET_CHILD_ID)
                ->where('category_id', 0)
                ->where('option_id', $row->option_id)
                ->exists();

            if (! $exists) {
                DB::table('category_child_option')->insert([
                    'child_id' => self::TARGET_CHILD_ID,
                    'category_id' => 0,
                    'option_id' => $row->option_id,
                    'reorder' => $row->reorder,
                ]);
            }
        }
    }

    public function down(): void
    {
        // Additive only, and indistinguishable after the fact from options a
        // seeder or an admin may have granted since — never withdraw here.
    }
};
