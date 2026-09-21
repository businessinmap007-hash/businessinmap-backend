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
    private function trustFor($request): array
    {
        $parties = \App\Http\Controllers\Api\V2\OrderTrustController::partyIds($this->resource);
        $viewer = \App\Support\BusinessContext::id($request);
        $out = [];

        foreach ($parties as $role => $id) {
            if (! $id || $id === $viewer) {
                continue;
            }
            $out[$role] = [
                'trusted_by_me' => \App\Models\PartyTrust::exists($viewer, $id),
                'trusts_me' => \App\Models\PartyTrust::exists($id, $viewer),
            ];
        }

        return $out;
    }

    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'prep_status' => $this->prep_status,
            'fulfillment_type' => (string) $this->fulfillment_type,
            'pickup_at' => optional($this->pickup_at)->toIso8601String(),
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

            // Out-of-city delivery pricing (null on an ordinary order): the
            // courier's proposal, and the merchant's recommendation on it.
            'delivery_fee_quote' => $this->delivery_fee_status ? [
                'status' => (string) $this->delivery_fee_status,
                'proposed_amount' => $this->delivery_fee_proposed !== null ? (float) $this->delivery_fee_proposed : null,
                'recommendation' => $this->delivery_fee_recommendation,
                'recommendation_note' => $this->delivery_fee_recommendation_note,
            ] : null,

            // Governorate shipping (null on an ordinary order).
            'shipping' => $this->shipping_status ? [
                'status' => (string) $this->shipping_status,
                'fee' => $this->shipping_fee !== null ? (float) $this->shipping_fee : null,
                'appointment_at' => optional($this->shipping_appointment_at)->toIso8601String(),
                'appointment_note' => $this->shipping_appointment_note,
                'appointment_confirmed_at' => optional($this->shipping_appointment_confirmed_at)->toIso8601String(),
                'to_governorate_id' => $this->shipping_to_governorate_id ? (int) $this->shipping_to_governorate_id : null,
                'company' => $this->shipping_company_id ? [
                    'id' => (int) $this->shipping_company_id,
                    'name' => optional($this->shippingCompany)->name,
                ] : null,
            ] : null,

            'payment_method' => $this->payment_method,
            'payment_status' => (string) ($this->payment_status ?? 'unpaid'),
            'paid_at' => optional($this->paid_at)->toIso8601String(),

            // Three independent cash-payment attestations for the no-wallet
            // COD flow - each party confirms only the leg of cash they're
            // party to. Never a chain: any subset can be set at once.
            // The viewer's own "I trust" ticks toward the other parties of the
            // order, plus whether each of them trusts the viewer. Detail views
            // only (items loaded) - a list row never needs it.
            // Cash orders are held to the three-party confirmation (completion
            // of pickup/dine-in, and reviews, wait on it).
            'payment_confirmation_required' => $this->requiresPaymentConfirmation(),
            'trust' => $this->relationLoaded('items') ? $this->trustFor($request) : null,
            'payment_confirmations' => [
                'customer_confirmed_at' => optional($this->customer_payment_confirmed_at)->toIso8601String(),
                'merchant_confirmed_at' => optional($this->merchant_payment_confirmed_at)->toIso8601String(),
                'driver_confirmed_at' => optional($this->driver_payment_confirmed_at)->toIso8601String(),
                'settled_at' => optional($this->payment_settled_at)->toIso8601String(),
            ],

            // Set at checkout from the merchant's own deposit_required_above
            // setting — advisory, nothing is held. See Order::needsExplicitDepositDecision().
            'deposit' => [
                'required' => (bool) $this->requires_deposit,
                'amount' => $this->deposit_amount !== null ? (float) $this->deposit_amount : null,
                'covered' => (bool) $this->deposit_covered,
                'covered_by' => $this->deposit_covered_by,
                'accepted_without_cover' => (bool) $this->deposit_accepted_without_cover,
                // Advisory deposit is released only once every party confirmed the cash.
                'released' => (bool) $this->requires_deposit && $this->payment_settled_at !== null,
            ],
            'address' => $this->address,
            'delivery_coordinates' => $this->customerLatLng()
                ? ['lat' => $this->customerLatLng()[0], 'lng' => $this->customerLatLng()[1]]
                : null,
            'delivery_driver' => $this->whenLoaded('deliveryDriver', fn () => $this->deliveryDriver ? [
                'id' => (int) $this->deliveryDriver->id,
                'name' => optional($this->deliveryDriver->user)->name,
                'phone' => $this->deliveryDriver->phone ?: optional($this->deliveryDriver->user)->phone,
                'vehicle_label' => $this->deliveryDriver->vehicle_label,
            ] : null),
            'notes' => $this->notes,
            'out_of_stock_policy' => $this->out_of_stock_policy,
            // Whether the business linked a project timeline to this order —
            // most orders (a coffee, a food delivery) never have one, so the
            // client only shows "view progress" when this is true.
            'has_project' => $this->relationLoaded('project') ? $this->project !== null : false,

            // Dine-in table (BIM-13.3): the business queue shows which table to serve.
            'business_table_id' => $this->business_table_id ? (int) $this->business_table_id : null,
            'table_label' => $this->whenLoaded(
                'businessTable',
                fn () => optional($this->businessTable)->label
            ),

            'business' => $this->whenLoaded('business', fn () => [
                'id' => (int) $this->business->id,
                'name' => $this->business->displayName(),
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
