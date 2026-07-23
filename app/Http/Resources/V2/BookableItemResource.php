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
            'code' => $this->code,
            'title' => $this->title,
            'capacity' => $this->capacity !== null ? (int) $this->capacity : null,
            'quantity' => (int) $this->quantity,
            'is_active' => (bool) $this->is_active,
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
