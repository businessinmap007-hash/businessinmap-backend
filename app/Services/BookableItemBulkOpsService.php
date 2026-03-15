<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableAvailabilitySlot;
use App\Models\BookablePriceRule;

class BookableItemBulkOpsService
{

    public function applyBlock(array $bookableIds, string $startsAt, string $endsAt): void
    {
        foreach ($bookableIds as $id) {

            BookableAvailabilitySlot::create([
                'bookable_item_id' => $id,
                'block_type' => 'blocked',
                'reason' => 'bulk_admin',
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

        }
    }

    public function applyPriceRule(array $bookableIds, string $startsAt, string $endsAt, float $price): void
    {
        foreach ($bookableIds as $id) {

            BookablePriceRule::create([
                'bookable_item_id' => $id,
                'rule_type' => 'override',
                'price' => $price,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

        }
    }

}
