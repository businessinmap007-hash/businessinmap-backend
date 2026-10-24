<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business's own bookable unit (room 101, table 5). Mirrors the web "My
 * bookable units" screen for the app/merchant client.
 */
class BookableItemResource extends JsonResource
{
    public function toArray($request): array
    {
        $service = $this->whenLoaded('service');

        return [
            'id' => (int) $this->id,
            'service' => [
                'id' => (int) $this->service_id,
                'key' => $service ? $service->key : null,
                'name' => $service ? $this->localize($service->name_ar, $service->name_en) : null,
            ],
            'item_type' => $this->item_type,
            // Which kind the unit is. The item type is «حجز إقامة» for every
            // room in the hotel; this is what tells 101 from جناح س301, and
            // what points the unit at its own priced row.
            'line_option' => $this->lineOptionPayload(),
            'code' => $this->code,
            'title' => $this->title,
            'label' => $this->resource->displayLabel(),
            // هذا مورِدُ صاحبِ المحل، فالملاحظةُ الداخلية مسموحةٌ هنا وحدها.
            // واجهةُ العميل (UnitDiscoveryController) لا ترسلها أبدًا.
            'description' => $this->description,
            'notes' => $this->notes,
            'images' => $this->whenLoaded('images', fn () => $this->resource->imagePayload(), []),
            'capacity' => $this->capacity !== null ? (int) $this->capacity : null,
            'quantity' => (int) $this->quantity,
            'is_active' => (bool) $this->is_active,
            // «أسعار للمصريين والمقيمين فقط»: all | locals | foreigners
            'guest_scope' => (string) ($this->guest_scope ?: 'all'),
            'reserve_rooms' => (int) ($this->reserve_rooms ?? 0),
            'status' => (string) ($this->status ?: \App\Models\BookableItem::STATUS_AVAILABLE),
            'is_currently_booked' => $this->resource->isCurrentlyBooked(),
            // «Day use» — only a room type of a stay has one
            'day_use' => $this->item_type === 'booking_stay' ? app(\App\Services\BookingDayUseService::class)->settings($this->resource) : null,
            // What the unit IS to its owner — a hotel's rooms, a rental company's cars, anything else just units — so the
            // screen speaks of cars to a car owner and keeps Day use for hotels.
            'unit_noun' => $this->unitNoun(),
            // «خصم الأسبوع والشهر» — a unit let by the period
            'stay_discounts' => $this->item_type === 'booking_stay' ? app(\App\Services\BookingStayDiscountService::class)->settings($this->resource) : null,
        ];
    }

    /** @return 'room'|'car'|'unit' */
    private function unitNoun(): string
    {
        $slug = \Illuminate\Support\Facades\DB::table('users as u')
            ->join('categories as c', 'c.id', '=', 'u.category_id')
            ->where('u.id', (int) $this->business_id)->value('c.slug');

        return match ($slug) {
            'tourist-hotels' => 'room',
            'cars' => 'car',
            default => 'unit',
        };
    }

    private function lineOptionPayload(): ?array
    {
        $option = $this->whenLoaded('lineOption');

        if (! $option || ! $option instanceof \App\Models\Option) {
            return $this->line_option_id ? ['id' => (int) $this->line_option_id, 'name' => null] : null;
        }

        return [
            'id' => (int) $option->id,
            'name' => $this->localize($option->name_ar, $option->name_en),
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
