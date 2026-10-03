<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;

/**
 * The steps that shape ONE menu the way furniture and fish were shaped (owner, 2026-10-04: «كل منيو
 * سيظهر حسب مجموعة الخيارات»), so a migration states WHAT a trade's menu is, not how rows are written:
 *
 *   • a detail KIND on the priced group (its per-item fields; no catalog unless said);
 *   • DESCRIBING groups for a trade — chosen per item, shown on its page, never priced;
 *   • a PRICE axis for a trade — one price per option;
 *   • withdrawing a group a trade should not carry («نظام التصنيع» on a shop that makes nothing).
 *
 * Everything is scoped to the trades named — placements are per trade, so a supermarket that carries
 * the same list is never asked about someone else's shelf. Idempotent, and a kind is assigned to its
 * group ONCE: an admin who sets the group back to basic in «أشكال المنيو» is never overruled.
 */
final class MenuShapeCurator
{
    public const MANUFACTURING = 394; // نظام التصنيع — a factory's price axis

    private int $menu;

    public function __construct()
    {
        $this->menu = (int) DB::table('platform_services')->where('key', 'menu')->value('id');
    }

    /** @param  list<array{0:string,1:string,2:string,3?:string}>  $attributes  [code, name_ar, name_en, data_type] added when missing */
    public function attributes(array $attributes): void
    {
        foreach ($attributes as $a) {
            if (DB::table('catalog_attributes')->where('code', $a[0])->exists()) {
                continue;
            }
            DB::table('catalog_attributes')->insert([
                'code' => $a[0], 'name_ar' => $a[1], 'name_en' => $a[2], 'data_type' => $a[3] ?? 'text', 'unit_id' => null,
                'is_filterable' => 1, 'is_variant_axis' => 0, 'is_required' => 0,
                'sort_order' => 400 + (int) DB::table('catalog_attributes')->max('id'), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<array{0:string,1:int,2:bool,3:bool,4:bool,5:bool}>  $fields  [attribute code, sort, on card, per item, filterable, on page]
     * @param  list<int>  $groupIds  the priced groups that take this kind
     */
    public function kind(string $code, string $nameAr, string $nameEn, array $fields, array $groupIds, bool $usesCatalog = false, int $sort = 100): int
    {
        $profile = DB::table('menu_detail_profiles')->where('code', $code)->value('id');
        if ($profile) {
            return (int) $profile;
        }

        $profile = (int) DB::table('menu_detail_profiles')->insertGetId([
            'code' => $code, 'name_ar' => $nameAr, 'name_en' => $nameEn, 'icon' => null, 'uses_catalog' => $usesCatalog,
            'sort_order' => $sort, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        foreach ($fields as [$attribute, $order, $card, $perItem, $filter, $page]) {
            $id = DB::table('catalog_attributes')->where('code', $attribute)->value('id');
            if (! $id) {
                continue;
            }
            DB::table('menu_detail_profile_attributes')->insert([
                'menu_detail_profile_id' => $profile, 'catalog_attribute_id' => $id, 'sort_order' => $order,
                'show_on_card' => $card, 'per_item' => $perItem, 'is_filterable' => $filter, 'show_on_page' => $page,
                'display' => 'auto', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        DB::table('option_groups')->whereIn('id', $groupIds)->whereNull('menu_detail_profile_id')->update(['menu_detail_profile_id' => $profile]);

        return $profile;
    }

    /**
     * A group of the trade's OWN words — «نوع البشرة». Created when missing, its options too (the English
     * name is unique platform-wide, so a clash gets a suffix instead of failing).
     *
     * @param  list<array{0:string,1:string}>  $options  [name_ar, name_en]
     * @param  list<int>  $trades  linked to these trades (category_id 0 = every root); [] = not linked (a price axis)
     */
    public function group(string $nameAr, string $nameEn, array $options, array $trades = []): int
    {
        $group = DB::table('option_groups')->where('name_ar', $nameAr)->value('id');
        if (! $group) {
            $group = DB::table('option_groups')->insertGetId([
                'name_ar' => $nameAr, 'name_en' => $nameEn, 'reorder' => 10300 + (int) DB::table('option_groups')->max('id') % 1000,
                'is_active' => 1, 'price_role' => 'modifier', 'menu_detail_profile_id' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach ($options as $i => [$ar, $en]) {
            $option = DB::table('options')->where('group_id', $group)->where('name_ar', $ar)->value('id');
            if (! $option) {
                $option = DB::table('options')->insertGetId([
                    'group_id' => $group, 'sort_order' => $i, 'name_ar' => $ar,
                    'name_en' => DB::table('options')->where('name_en', $en)->exists() ? $en . ' (' . $nameEn . ')' : $en,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            foreach ($trades as $trade) {
                DB::table('category_child_option')->updateOrInsert(['child_id' => $trade, 'category_id' => 0, 'option_id' => $option], ['reorder' => ($i + 1) * 10]);
            }
        }

        return (int) $group;
    }

    /** An EXISTING group (or one made with {@see group()}) describes items of these trades. */
    public function describe(int $groupId, array $trades, int $sort, string $display = 'chips', bool $multiple = false): void
    {
        foreach ($trades as $trade) {
            $this->place($groupId, $trade, 'descriptive', $sort, $display, $multiple);
            // Its options belong to the trade, or the merchant could not pick them.
            foreach (DB::table('options')->where('group_id', $groupId)->orderBy('id')->pluck('id') as $i => $option) {
                // Linked under the roots the trade already uses is not enough: a merchant standing under another
                // root of the same trade would never be offered it. The shared row (0) reaches every root.
                if (! DB::table('category_child_option')->where('child_id', $trade)->where('option_id', $option)->where('category_id', 0)->exists()) {
                    DB::table('category_child_option')->insert(['child_id' => $trade, 'category_id' => 0, 'option_id' => $option, 'reorder' => ($i + 1) * 10]);
                }
            }
        }
    }

    public function priceAxis(int $groupId, array $trades, int $sort = 0): void
    {
        foreach ($trades as $trade) {
            $this->place($groupId, $trade, 'price_variant', $sort, 'auto', true);
        }
    }

    /** The trade no longer carries [$groupId] under [$usage] — an inactive row, so a re-run of a seeder cannot re-add it. */
    public function withdraw(int $groupId, array $trades, string $usage): void
    {
        foreach ($trades as $trade) {
            DB::table('service_option_group_placements')
                ->where('platform_service_id', $this->menu)->where('child_id', $trade)->where('option_group_id', $groupId)->where('usage', $usage)
                ->update(['is_active' => 0, 'updated_at' => now()]);
        }
    }

    /**
     * The trade stops offering a group's options at all (its chips as well as its price axis), and the
     * decision is recorded so no seeder grants them back — see {@see ChildOptionDecisions}.
     */
    public function unlink(int $groupId, array $trades): void
    {
        $options = DB::table('options')->where('group_id', $groupId)->pluck('id')->all();
        foreach ($trades as $trade) {
            DB::table('category_child_option')->where('child_id', $trade)->whereIn('option_id', $options)->delete();
            app(ChildOptionDecisions::class)->record($trade, ChildOptionDecisions::ALL_ROOTS, $options, 'menu-shapes');
        }
        $this->withdraw($groupId, $trades, 'price_variant');
    }

    private function place(int $groupId, int $trade, string $usage, int $sort, string $display, bool $multiple): void
    {
        DB::table('service_option_group_placements')->updateOrInsert(
            // One placement per group per trade (sogp_scope_unique): a group that was a shelf or a
            // price axis and now DESCRIBES is that same row changing its usage, not a second row.
            ['platform_service_id' => $this->menu, 'option_group_id' => $groupId, 'child_id' => $trade, 'item_type_key' => ''],
            ['usage' => $usage, 'branches_as_sections' => 0, 'is_active' => 1, 'sort_order' => $sort, 'show_on_page' => 1, 'display' => $display, 'multiple' => $multiple, 'created_at' => now(), 'updated_at' => now()]
        );
    }
}
