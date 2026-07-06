<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BusinessServicePrice;
use App\Models\User;

/**
 * Single source for resolving the BusinessServicePrice that backs a given
 * (business + service + subcategory + item type). Pricing lives only in
 * business_service_prices (per item type); bookable_items are inventory only.
 *
 * Extracted so both the booking price/deposit path (ServiceExecutionEngine)
 * and the per-day calendar (BookablePricingService) resolve the base price the
 * same way, without a circular dependency between those two services.
 */
class BusinessServicePriceResolver
{
    /**
     * Resolve the active BusinessServicePrice for a context, with the same
     * priority the booking engine uses:
     *   1) same child + same item type
     *   2) same child + category default type
     *   3) same child + any legacy price
     *   4) no child + same item type
     *   5) no child + category default type
     *   6) no child + any legacy price
     */
    public function resolve(
        int $businessId,
        int $serviceId,
        int $childId = 0,
        ?string $itemType = null
    ): ?BusinessServicePrice {
        if ($businessId <= 0 || $serviceId <= 0) {
            return null;
        }

        $itemType = trim((string) $itemType);
        $defaultItemType = BusinessServicePrice::DEFAULT_ITEM_TYPE;

        $find = function (?int $child, ?string $type) use ($businessId, $serviceId): ?BusinessServicePrice {
            $query = BusinessServicePrice::query()
                ->where('business_id', $businessId)
                ->where('service_id', $serviceId)
                ->where('is_active', 1);

            if ($child !== null) {
                $query->where('child_id', $child);
            }

            if ($type !== null && $type !== '') {
                $query->where('bookable_item_type', $type);
            }

            return $query->orderByDesc('id')->first();
        };

        if ($childId > 0) {
            if ($itemType !== '') {
                if ($row = $find($childId, $itemType)) {
                    return $row;
                }
            }

            if ($itemType !== $defaultItemType) {
                if ($row = $find($childId, $defaultItemType)) {
                    return $row;
                }
            }

            if ($row = $find($childId, null)) {
                return $row;
            }
        }

        if ($itemType !== '') {
            if ($row = $find(null, $itemType)) {
                return $row;
            }
        }

        if ($itemType !== $defaultItemType) {
            if ($row = $find(null, $defaultItemType)) {
                return $row;
            }
        }

        return $find(null, null);
    }

    /**
     * Resolve the BusinessServicePrice for a physical bookable unit, deriving
     * the subcategory from the unit's business.
     */
    public function resolveForBookableItem(BookableItem $item): ?BusinessServicePrice
    {
        $childId = (int) (User::query()
            ->where('id', (int) $item->business_id)
            ->value('category_child_id') ?? 0);

        return $this->resolve(
            businessId: (int) $item->business_id,
            serviceId: (int) $item->service_id,
            childId: $childId,
            itemType: trim((string) ($item->item_type ?? '')) ?: null
        );
    }
}
