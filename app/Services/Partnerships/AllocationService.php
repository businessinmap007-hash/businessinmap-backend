<?php

namespace App\Services\Partnerships;

use App\Models\BookableAllocation;
use App\Models\BookableItem;
use App\Models\BusinessPartnership;
use App\Models\BusinessServicePrice;
use App\Services\BusinessServicePriceResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * The first contract kind of a partnership: the owner (a hotel) gives its partner (a travel agency) a QUANTITY of one of its
 * rooms at a CONTRACT price, and the partner sells it at its own price — never below the contract price.
 *
 * What the two parties see here is private to them (the contract price, the quantities, the dates). The public only ever sees the
 * result: a room offered by the partner at the partner's final price (UnitDiscoveryController, HotelRoomSearchController).
 */
final class AllocationService
{
    public function __construct(private readonly BusinessServicePriceResolver $prices)
    {
    }

    /** @return Collection<int,BookableAllocation> */
    public function of(BusinessPartnership $p): Collection
    {
        return BookableAllocation::query()->where('partnership_id', $p->id)->with(['bookableItem.lineOption:id,name_ar,name_en'])->orderByDesc('id')->get();
    }

    /** The owner grants a quantity of one of its rooms. @param array<string,mixed> $data */
    public function grant(BusinessPartnership $p, array $data): BookableAllocation
    {
        $this->assertActive($p);

        $unit = BookableItem::query()->where('business_id', $p->owner_business_id)->where('is_active', 1)->find((int) $data['bookable_item_id']);

        if (! $unit) {
            throw ValidationException::withMessages(['bookable_item_id' => __('هذه الوحدة ليست من وحدات المالك.')]);
        }

        $quantity = (int) $data['quantity_total'];
        $capacity = max((int) $unit->quantity, (int) $unit->rooms()->count(), 1);
        $given = (int) BookableAllocation::query()->where('bookable_item_id', $unit->id)
            ->whereIn('status', [BookableAllocation::STATUS_ACTIVE, BookableAllocation::STATUS_PAUSED])->sum('quantity_total');

        if ($given + $quantity > $capacity) {
            throw ValidationException::withMessages(['quantity_total' => __('الكمية تتجاوز ما تبقى من هذه الوحدة (:left).', ['left' => max($capacity - $given, 0)])]);
        }

        $price = $this->prices->resolveForBookableItem($unit);

        return BookableAllocation::create([
            'partnership_id' => (int) $p->id,
            'owner_business_id' => (int) $p->owner_business_id,
            'partner_business_id' => (int) $p->partner_business_id,
            'bookable_item_id' => (int) $unit->id,
            'platform_service_id' => (int) $unit->service_id,
            'allocation_type' => (string) ($data['allocation_type'] ?? BookableAllocation::TYPE_GUARANTEED),
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'quantity_total' => $quantity,
            'quantity_sold' => 0, 'quantity_reserved' => 0, 'quantity_released' => 0,
            'release_days_before' => (int) ($data['release_days_before'] ?? 0),
            'min_nights' => $data['min_nights'] ?? null,
            'max_nights' => $data['max_nights'] ?? null,
            'contract_price' => round((float) $data['contract_price'], 2),
            'currency' => (string) ($data['currency'] ?? ($price->currency ?? 'EGP')),
            'markup_type' => 'none',
            'markup_value' => 0,
            'status' => BookableAllocation::STATUS_ACTIVE,
        ])->load('bookableItem.lineOption:id,name_ar,name_en');
    }

    /** The owner changes the terms of a grant. @param array<string,mixed> $data */
    public function update(BookableAllocation $a, array $data): BookableAllocation
    {
        $fields = array_intersect_key($data, array_flip(['starts_at', 'ends_at', 'release_days_before', 'min_nights', 'max_nights', 'contract_price', 'allocation_type']));

        if (isset($data['quantity_total'])) {
            $quantity = (int) $data['quantity_total'];

            if ($quantity < (int) $a->quantity_sold + (int) $a->quantity_reserved) {
                throw ValidationException::withMessages(['quantity_total' => __('الكمية أقل مما بيع أو حُجز بالفعل.')]);
            }

            $fields['quantity_total'] = $quantity;
        }

        $a->update($fields);

        return $a->fresh('bookableItem.lineOption:id,name_ar,name_en');
    }

    public function setStatus(BookableAllocation $a, string $status): BookableAllocation
    {
        $a->update(['status' => $status]);

        return $a->fresh('bookableItem.lineOption:id,name_ar,name_en');
    }

    /**
     * The PARTNER prices what it was given: a percentage or an amount on top of the contract price. Never below it — a markup of
     * zero is the lowest, which is the contract price itself.
     */
    public function price(BookableAllocation $a, string $type, float $value): BookableAllocation
    {
        if (! in_array($type, ['none', 'fixed', 'percent'], true) || $value < 0 || ($type === 'percent' && $value > 300)) {
            throw ValidationException::withMessages(['markup_value' => __('الهامش لا يقل عن صفر، والنسبة حتى ٣٠٠٪.')]);
        }

        $a->update(['markup_type' => $type, 'markup_value' => $type === 'none' ? 0 : round($value, 2)]);

        return $a->fresh('bookableItem.lineOption:id,name_ar,name_en');
    }

    /**
     * One allocation as its two parties read it, with the room's card for a trip programme: what the room is, its photos and what
     * it carries. The contract price and the markup are the parties' alone.
     *
     * @return array<string,mixed>
     */
    public function present(BookableAllocation $a): array
    {
        $unit = $a->bookableItem;

        return [
            'id' => (int) $a->id,
            'partnership_id' => (int) $a->partnership_id,
            'status' => (string) $a->status,
            'allocation_type' => (string) $a->allocation_type,
            'quantity_total' => (int) $a->quantity_total,
            'quantity_sold' => (int) $a->quantity_sold,
            'quantity_reserved' => (int) $a->quantity_reserved,
            'quantity_available' => $a->availableQuantity(),
            'contract_price' => round((float) $a->contract_price, 2),
            'markup_type' => (string) ($a->markup_type ?: 'none'),
            'markup_value' => round((float) $a->markup_value, 2),
            'final_price' => round($a->finalPrice(), 2),
            'currency' => (string) ($a->currency ?: 'EGP'),
            'starts_at' => optional($a->starts_at)->toIso8601String(),
            'ends_at' => optional($a->ends_at)->toIso8601String(),
            'min_nights' => $a->min_nights,
            'max_nights' => $a->max_nights,
            'release_days_before' => (int) $a->release_days_before,
            // in how many of the partner's trip programmes this room's card sits (the owner sees only that, never the programmes)
            'programs_count' => (int) \App\Models\TripProgramItem::query()->join('trip_program_days as d', 'd.id', '=', 'trip_program_items.day_id')->where('trip_program_items.allocation_id', $a->id)->distinct()->count('d.program_id'),
            'room' => $unit ? $this->card($unit) : null,
        ];
    }

    /**
     * The room as a customer of the PARTNER reads it: the card, the partner's own price, who it comes from and what is left. The
     * contract price, the markup and the quantities of the grant are never in it.
     *
     * @return array<string,mixed>
     */
    public function publicRoom(BookableAllocation $a): array
    {
        $unit = $a->bookableItem;

        return $this->card($unit) + [
            'allocation_id' => (int) $a->id,
            'bookable_id' => (int) $unit->id,
            'service_id' => (int) $unit->service_id,
            'item_type' => (string) $unit->item_type,
            'price' => round($a->finalPrice(), 2),
            'currency' => (string) ($a->currency ?: 'EGP'),
            'from' => $a->ownerBusiness ? ['id' => (int) $a->ownerBusiness->id, 'name' => (string) $a->ownerBusiness->name] : null,
            'available_quantity' => $a->availableQuantity(),
            'available' => $a->availableQuantity() > 0,
        ];
    }

    /** The room card: the same thing the hotel shows its own guests, ready to be placed in a programme. @return array<string,mixed> */
    public function card(BookableItem $unit): array
    {
        $unit->loadMissing('lineOption:id,name_ar,name_en');

        return [
            'unit_id' => (int) $unit->id,
            'label' => (string) $unit->displayLabel(),
            'kind' => $unit->lineOption ? (string) ($unit->lineOption->name_ar ?: $unit->lineOption->name_en) : null,
            'description' => $unit->description,
            'capacity' => $unit->capacity !== null ? (int) $unit->capacity : null,
            'images' => $unit->imagePayload(),
            'features' => $unit->offeringOptions()->where('role', '!=', 'line')->with('option:id,name_ar,name_en')->get()->map(fn ($o) => $o->option ? (string) ($o->option->name_ar ?: $o->option->name_en) : null)->filter()->values()->all(),
        ];
    }

    private function assertActive(BusinessPartnership $p): void
    {
        if ($p->status !== BusinessPartnership::STATUS_ACTIVE) {
            throw ValidationException::withMessages(['partnership' => __('الشراكة غير فعّالة.')]);
        }
    }
}
