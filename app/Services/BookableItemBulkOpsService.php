<?php

namespace App\Services;

use App\Models\BookableItem;
use App\Models\BookableItemBlockedSlot;
use App\Models\BookableItemPriceRule;
use Illuminate\Support\Facades\DB;

class BookableItemBulkOpsService
{
    public function applyBlock(
        array $bookableIds,
        string $startsAt,
        string $endsAt,
        string $reason = 'bulk_admin',
        ?string $notes = null,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use ($bookableIds, $startsAt, $endsAt, $reason, $notes, $actorId) {
            $items = BookableItem::query()
                ->whereIn('id', $bookableIds)
                ->get(['id', 'business_id', 'service_id']);

            foreach ($items as $item) {
                BookableItemBlockedSlot::create([
                    'bookable_item_id' => (int) $item->id,
                    'business_id' => (int) $item->business_id,
                    'platform_service_id' => (int) $item->service_id,
                    'block_type' => BookableItemBlockedSlot::TYPE_ADMIN,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'reason' => $reason,
                    'notes' => $notes,
                    'created_by' => $actorId,
                    'is_active' => true,
                ]);
            }
        });
    }

    public function applyPriceRule(
        array $bookableIds,
        string $startDate,
        string $endDate,
        string $priceType,
        float $priceValue,
        ?string $title = null,
        ?string $notes = null,
        int $priority = 100,
        ?int $actorId = null
    ): void {
        DB::transaction(function () use (
            $bookableIds,
            $startDate,
            $endDate,
            $priceType,
            $priceValue,
            $title,
            $notes,
            $priority,
            $actorId
        ) {
            $items = BookableItem::query()
                ->whereIn('id', $bookableIds)
                ->get(['id', 'business_id', 'service_id']);

            foreach ($items as $item) {
                BookableItemPriceRule::create([
                    'bookable_item_id' => (int) $item->id,
                    'business_id' => (int) $item->business_id,
                    'platform_service_id' => (int) $item->service_id,
                    'rule_type' => BookableItemPriceRule::RULE_DATE_RANGE,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'price_type' => $priceType,
                    'price_value' => $priceValue,
                    'currency' => 'EGP',
                    'priority' => $priority,
                    'title' => $title ?: 'Bulk rule',
                    'notes' => $notes,
                    'created_by' => $actorId,
                    'is_active' => true,
                ]);
            }
        });
    }
}