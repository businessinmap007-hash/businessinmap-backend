<?php

namespace App\Support;

use App\Models\User;

/**
 * Which businesses get «تعبئة الرفوف» — one rule, read from three places that
 * used to duplicate it (the nav gate, the fill screen's guard, and the
 * customer-facing heading), which is exactly how a rename or a widened scope
 * silently drifts into agreeing with only two of them.
 *
 * ── من التلاتة إلى كل تاجر سلعة جاهزة ────────────────────────────────────────
 *
 * Started scoped to three hardcoded ids — هايبر ماركت، مني ماركت، سوبر ماركت
 * — the day the owner asked for it. On 2026-08-26 he asked for «كل الأبناء»,
 * and the answer he chose narrowing that was: every trade that sells a
 * READY-MADE product by the platform's own reckoning, not a custom one typed
 * and photographed by hand under a heading — «تجار السلع الجاهزة فقط».
 *
 * That reckoning already exists and needed no new curation: `menu_market` is
 * the item-type the menu service itself was built with to mean exactly this —
 * a supermarket, a hardware shop, a spare-parts dealer, a pharmacy — sixty
 * eight children carry it already, the three markets among them. A restaurant
 * (`menu_food`), a furniture showroom (`menu_furniture`), a dealer
 * (`menu_vehicles`) and an estate office (`menu_properties`) do not, because a
 * dish or a bedroom set or a flat is not one shelf-stable row a `line` option
 * already names — it is several distinct things a merchant describes himself
 * under that heading.
 *
 * @see \App\Support\BusinessPanelNav::menuKindsOf()  the same reading the menu
 *      nav already uses to name a business's catalog
 */
final class MarketCatalogChildren
{
    public const ITEM_TYPE = 'menu_market';

    public static function includes(?User $business): bool
    {
        if (! $business || (int) ($business->category_child_id ?? 0) <= 0) {
            return false;
        }

        return in_array('menu', BusinessPanelNav::servicesOf($business), true)
            && in_array(self::ITEM_TYPE, BusinessPanelNav::menuKindsOf($business), true);
    }
}
