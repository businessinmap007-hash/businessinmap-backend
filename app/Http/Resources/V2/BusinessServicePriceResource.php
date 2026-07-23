<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A business's own price row for one (service, item type). Mirrors the web
 * "My prices" screen for the app/merchant client.
 */
class BusinessServicePriceResource extends JsonResource
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
            'bookable_item_type' => $this->bookable_item_type,
            'price' => (float) $this->price,
            'charge_mode' => $this->charge_mode,
            'charge_amount' => (float) $this->charge_amount,
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'discount_enabled' => (bool) $this->discount_enabled,
            'discount_percent' => (int) $this->discount_percent,
        ];
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
