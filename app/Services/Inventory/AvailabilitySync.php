<?php

namespace App\Services\Inventory;

use App\Models\BusinessCatalogListing;
use App\Models\Inventory\Level;
use App\Models\Inventory\RecipeLine;
use App\Models\MenuItem;

/**
 * For a business that keeps its stock here, the quantity the customer sees on a menu item or a retail listing is no
 * longer typed by hand — it is what the ledger can make: the smallest «on hand ÷ need» over the item's recipe (one line
 * for a can sold as a can, several for a burger). Items with no recipe keep the merchant's own number, untouched.
 */
final class AvailabilitySync
{
    /** @param  list<int>  $itemIds */
    public function refresh(int $businessId, array $itemIds): void
    {
        $owners = RecipeLine::query()
            ->where('business_id', $businessId)
            ->whereIn('item_id', $itemIds)
            ->whereIn('owner_type', [RecipeService::MENU_ITEM, RecipeService::RETAIL_LISTING])
            ->get(['owner_type', 'owner_id'])
            ->unique(fn ($l) => $l->owner_type . ':' . $l->owner_id);

        foreach ($owners as $owner) {
            $available = $this->canMake((string) $owner->owner_type, (int) $owner->owner_id);

            if ($available === null) {
                continue;
            }

            if ($owner->owner_type === RecipeService::MENU_ITEM) {
                $menuItem = MenuItem::query()->where('business_id', $businessId)->find($owner->owner_id);
                if ($menuItem && (int) $menuItem->available_quantity !== $available) {
                    $menuItem->available_quantity = $available;
                    $menuItem->save();
                }
            } else {
                BusinessCatalogListing::query()->where('business_id', $businessId)->whereKey($owner->owner_id)
                    ->where(fn ($q) => $q->whereNull('stock')->orWhere('stock', '!=', $available))
                    ->update(['stock' => $available]);
            }
        }
    }

    public function canMake(string $ownerType, int $ownerId): ?int
    {
        $lines = RecipeLine::query()->where('owner_type', $ownerType)->where('owner_id', $ownerId)->get();

        if ($lines->isEmpty()) {
            return null;
        }

        $min = null;

        foreach ($lines as $line) {
            $need = RecipeService::effective((float) $line->qty, (float) $line->waste_pct);
            if ($need <= 0) {
                continue;
            }
            $onHand = (float) Level::query()->where('item_id', $line->item_id)->sum('qty');
            $can = (int) max(0, floor(round($onHand / $need, 6)));
            $min = $min === null ? $can : min($min, $can);
        }

        return $min;
    }
}
