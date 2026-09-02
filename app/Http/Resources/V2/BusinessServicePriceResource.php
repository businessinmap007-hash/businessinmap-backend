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
            // what this price actually sells: «كشف — عظام» rather than «كشف»
            'line_option' => $this->optionPayload($this->resource->lineOption()),
            'modifier_options' => $this->resource->modifierOptions()
                ->map(fn ($o) => $this->optionPayload($o))->values(),
            // What each modifier currently adds — otherwise an edit screen has
            // no way to prefill these, and resubmitting the modifier without a
            // value would silently reset it to 0. Keyed by option_id, same
            // shape the store/update endpoints accept back.
            'modifier_adjust' => (object) $this->resource->currentOfferingAdjustments(),
            'label' => $this->resource->offeringLabel(),
            'price' => (float) $this->price,
            'charge_mode' => $this->charge_mode,
            'charge_amount' => (float) $this->charge_amount,
            'duration_minutes' => $this->duration_minutes !== null ? (int) $this->duration_minutes : null,
            'currency' => $this->currency,
            'is_active' => (bool) $this->is_active,
            'discount_enabled' => (bool) $this->discount_enabled,
            'discount_percent' => (int) $this->discount_percent,
        ];
    }

    private function optionPayload($option): ?array
    {
        return $option ? [
            'id' => (int) $option->id,
            'name' => $this->localize($option->name_ar, $option->name_en),
        ] : null;
    }

    private function localize(?string $ar, ?string $en): ?string
    {
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return ($primary !== null && $primary !== '') ? $primary : (($ar ?: $en) ?: null);
    }
}
