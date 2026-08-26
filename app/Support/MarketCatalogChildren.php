<?php

namespace App\Support;

/**
 * The three children «تعبئة الرفوف» was built for — one list, read from three
 * places that used to duplicate it (the nav gate, the fill screen's guard, and
 * the customer-facing heading below), which is exactly how a rename or a
 * fourth market silently drifts into agreeing with only two of them.
 *
 * «هايبر ماركت والسوبر ماركت والمني ماركت» — المالك، 2026-08-25/26.
 */
final class MarketCatalogChildren
{
    public const IDS = [149, 185, 272];

    public static function includes(?int $childId): bool
    {
        return $childId !== null && in_array($childId, self::IDS, true);
    }
}
