<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A placed order for the mobile app — used for both the customer's order
 * history and the business's incoming-order queue. Items are included only
 * when the `items` relation is eager-loaded (list views omit them).
 */
class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'prep_status' => $this->prep_status,
            'fulfillment_type' => (string) $this->fulfillment_type,
            'delivery_stage' => $this->delivery_stage,
            'is_shared' => (bool) $this->is_shared,

            'totals' => [
                'total' => (float) $this->total,
                'delivery_fee' => (float) $this->delivery_fee,
                'discount' => (float) $this->discount,
                'service_fee' => (float) $this->service_fee,
                'tax' => (float) $this->tax,
                'final_total' => (float) $this->final_total,
            ],

            'payment_method' => $this->payment_method,
            'payment_status' => (string) ($this->payment_status ?? 'unpaid'),
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'address' => $this->address,
            'notes' => $this->notes,

            'business' => $this->whenLoaded('business', fn () => [
                'id' => (int) $this->business->id,
                'name' => (string) $this->business->name,
                'logo' => $this->business->logo ?: null,
            ]),
            'customer' => $this->whenLoaded('user', fn () => [
                'id' => (int) $this->user->id,
                'name' => (string) $this->user->name,
                'phone' => $this->user->phone,
            ]),

            'items_count' => $this->when($this->items_count !== null, fn () => (int) $this->items_count),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),

            'handover_confirmed_at' => optional($this->handover_confirmed_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
