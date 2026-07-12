<?php

namespace Tests\Concerns;

use App\Models\MenuItem;
use App\Models\MenuItemExtra;
use App\Models\MenuItemVariant;
use App\Models\MenuSection;

/**
 * Builds menu fixtures (section, item, variant, extra) for menu feature tests.
 * Use under DatabaseTransactions so every row is rolled back.
 */
trait SeedsMenu
{
    protected function seedSection(int $businessId, string $nameAr = 'أطباق رئيسية', int $sort = 0): MenuSection
    {
        return MenuSection::create([
            'business_id' => $businessId,
            'name_ar' => $nameAr,
            'sort_order' => $sort,
            'is_active' => 1,
        ]);
    }

    protected function seedMenuItem(int $businessId, ?int $sectionId = null, float $base = 50.0, string $nameAr = 'برجر لحم'): MenuItem
    {
        return MenuItem::create([
            'business_id' => $businessId,
            'menu_section_id' => $sectionId,
            'name_ar' => $nameAr,
            'base_price' => $base,
            'is_active' => 1,
            'sort_order' => 0,
        ]);
    }

    protected function seedVariant(MenuItem $item, string $nameAr = 'كبير', ?float $price = null, ?float $delta = null, bool $default = false): MenuItemVariant
    {
        return MenuItemVariant::create([
            'menu_item_id' => $item->id,
            'type' => 'size',
            'name_ar' => $nameAr,
            'price' => $price,
            'price_delta' => $delta,
            'is_default' => $default,
            'is_active' => 1,
        ]);
    }

    protected function seedExtra(MenuItem $item, string $nameAr = 'جبنة زيادة', float $price = 10.0, int $maxQty = 3): MenuItemExtra
    {
        return MenuItemExtra::create([
            'menu_item_id' => $item->id,
            'group_key' => null,
            'name_ar' => $nameAr,
            'price' => $price,
            'max_qty' => $maxQty,
            'is_active' => 1,
        ]);
    }
}
