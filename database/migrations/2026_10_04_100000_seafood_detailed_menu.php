<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «ابدأ بمنيو الأسماك والمأكولات البحرية» — المالك، 2026-10-04: a menu shows what its option
 * group needs, the way the furniture menu was shaped.
 *
 * Before: a fish shop's «إضافة صنف» asked a name, a price and a quantity, offered «نظام التصنيع»
 * («تصنيع حسب الطلب»…) as its price axis, and described nothing.
 *
 * Now, the same shape furniture has:
 *   • a detail kind «أسماك ومأكولات بحرية» (no catalog — the merchant names the item) on the group,
 *     with «عدد القطع فى الكيلو» per item (the real grading of shrimp, sardines) and «الوصف»;
 *   • DESCRIBING groups for the fish trade — حالة السمك، مصدر السمك، حجم السمك — chosen per item,
 *     shown on its card/page, never priced (a frozen and a fresh row are two lines: quantity lives
 *     on the line);
 *   • a PRICE axis «تجهيز السمك» (كامل، منظف، فيليه…) — one price per preparation — replacing
 *     «نظام التصنيع», which a fish shop does not have.
 * Scoped to the fish shop (#101) only: placements are per trade, so a supermarket that also carries
 * the fish list is not asked about its rice's «حجم السمك».
 */
return new class extends Migration
{
    private const GROUP = 829;     // أنواع الأسماك والمأكولات البحرية
    private const FISH_SHOP = 101; // أسماك
    private const MANUFACTURING = 394;

    public function up(): void
    {
        $now = now();
        $menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
        if ($menu <= 0 || ! DB::table('option_groups')->where('id', self::GROUP)->exists()) {
            return;
        }

        // ── the detail kind ────────────────────────────────────────────────
        $perKg = DB::table('catalog_attributes')->where('code', 'pieces_per_kg')->value('id');
        if (! $perKg) {
            $perKg = DB::table('catalog_attributes')->insertGetId([
                'code' => 'pieces_per_kg', 'name_ar' => 'عدد القطع فى الكيلو', 'name_en' => 'Pieces per kg',
                'data_type' => 'number', 'unit_id' => null, 'is_filterable' => 1, 'is_variant_axis' => 0,
                'is_required' => 0, 'sort_order' => 310, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $description = (int) DB::table('catalog_attributes')->where('code', 'description')->value('id');

        $profile = DB::table('menu_detail_profiles')->where('code', 'seafood')->value('id');
        if (! $profile) {
            $profile = DB::table('menu_detail_profiles')->insertGetId([
                'code' => 'seafood', 'name_ar' => 'أسماك ومأكولات بحرية', 'name_en' => 'Seafood',
                'icon' => null, 'uses_catalog' => 0, 'sort_order' => 80, 'is_active' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $fields = [[$perKg, 10, 1, 1, 1, 1]];
            if ($description) {
                $fields[] = [$description, 900, 0, 0, 0, 1];
            }
            foreach ($fields as [$attribute, $sort, $card, $perItem, $filter, $page]) {
                DB::table('menu_detail_profile_attributes')->insert([
                    'menu_detail_profile_id' => $profile, 'catalog_attribute_id' => $attribute, 'sort_order' => $sort,
                    'show_on_card' => $card, 'per_item' => $perItem, 'is_filterable' => $filter, 'show_on_page' => $page,
                    'display' => 'auto', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            // Assigned ONCE — an admin who sets the group back to basic is never overruled.
            DB::table('option_groups')->where('id', self::GROUP)->whereNull('menu_detail_profile_id')->update(['menu_detail_profile_id' => $profile]);
        }

        // ── describing groups + the price axis, for the fish shop only ─────
        $describing = [
            ['حالة السمك', 'Fish condition', 10, 'chips', 0, [['سمك طازج', 'Fresh fish'], ['سمك مبرد', 'Chilled fish'], ['سمك مجمد', 'Frozen fish']]],
            ['مصدر السمك', 'Fish source', 20, 'chips', 0, [['من البحر', 'From the sea'], ['من النهر', 'From the river'], ['استزراع سمكي', 'Farmed fish'], ['مستورد', 'Imported fish']]],
            ['حجم السمك', 'Fish size', 30, 'chips', 0, [['حجم صغير', 'Small size'], ['حجم متوسط', 'Medium size'], ['حجم كبير', 'Large size'], ['حجم جامبو', 'Jumbo size']]],
        ];
        $axis = ['تجهيز السمك', 'Fish preparation', 0, 'auto', 1, [['كامل بالأحشاء', 'Whole, ungutted'], ['منظف جاهز للطهي', 'Cleaned, ready to cook'], ['فيليه', 'Fillet'], ['شرائح وتقطيع', 'Steaks and cuts'], ['سمك مفروم', 'Minced fish']]];

        foreach ([[$describing[0], 'descriptive'], [$describing[1], 'descriptive'], [$describing[2], 'descriptive'], [$axis, 'price_variant']] as [$spec, $usage]) {
            [$nameAr, $nameEn, $sort, $display, $multiple, $options] = $spec;

            $group = DB::table('option_groups')->where('name_ar', $nameAr)->value('id');
            if (! $group) {
                $group = DB::table('option_groups')->insertGetId([
                    'name_ar' => $nameAr, 'name_en' => $nameEn, 'reorder' => 10300 + $sort, 'is_active' => 1,
                    'price_role' => 'modifier', 'menu_detail_profile_id' => null, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            foreach ($options as $i => [$optionAr, $optionEn]) {
                $option = DB::table('options')->where('group_id', $group)->where('name_ar', $optionAr)->value('id');
                if (! $option) {
                    $option = DB::table('options')->insertGetId([
                        'group_id' => $group, 'sort_order' => $i, 'name_ar' => $optionAr,
                        'name_en' => DB::table('options')->where('name_en', $optionEn)->exists() ? $optionEn . ' (fish)' : $optionEn,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
                // A describing group's options belong to the trade (category_id 0 = shared across
                // every root the fish shop stands under). A PRICE axis' do not: linked, they would
                // be offered as «what is it» lines beside the fish; the axis reads its options directly.
                if ($usage === 'descriptive') {
                    DB::table('category_child_option')->updateOrInsert(
                        ['child_id' => self::FISH_SHOP, 'category_id' => 0, 'option_id' => $option],
                        ['reorder' => ($i + 1) * 10]
                    );
                }
            }

            DB::table('service_option_group_placements')->updateOrInsert(
                ['platform_service_id' => $menu, 'option_group_id' => $group, 'child_id' => self::FISH_SHOP, 'item_type_key' => '', 'usage' => $usage],
                ['branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => $sort, 'show_on_page' => 1, 'display' => $display, 'multiple' => $multiple, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // «نظام التصنيع» is not how a fish shop prices: the trade's own row says so (inactive, so a
        // later seeder run cannot re-add it), and the fish descriptors lead the form.
        DB::table('service_option_group_placements')
            ->where('platform_service_id', $menu)->where('child_id', self::FISH_SHOP)->where('usage', 'price_variant')
            ->where('option_group_id', self::MANUFACTURING)->update(['is_active' => 0, 'updated_at' => $now]);
        DB::table('service_option_group_placements')
            ->where('platform_service_id', $menu)->where('child_id', self::FISH_SHOP)->where('usage', 'descriptive')
            ->where('sort_order', 0)->whereNotIn('option_group_id', DB::table('option_groups')->whereIn('name_ar', ['حالة السمك', 'مصدر السمك', 'حجم السمك'])->pluck('id'))
            ->update(['sort_order' => 60, 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Curated data; the admin owns it from here.
    }
};
