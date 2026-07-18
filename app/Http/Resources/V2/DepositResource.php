<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What a party to an escrow deposit may see about it.
 *
 * v1 returned the raw model — all ~50 columns, including the counterparty's
 * wallet transaction ids, the external-proof path, the verifier's user id and
 * the whole policy snapshot. This exposes the money and the state, and stops
 * there.
 */
class DepositResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewerId = (int) ($request->user()->id ?? 0);
        $isClient = (int) $this->client_id === $viewerId;

        return [
            'id' => (int) $this->id,
            'status' => $this->status?->value ?? $this->status,
            // Which side of this deposit the caller is on — the app needs it to
            // phrase "you paid" vs "you are holding".
            'my_role' => $isClient ? 'client' : 'business',
            'total_amount' => (float) $this->total_amount,
            'client_amount' => (float) $this->client_amount,
            'business_amount' => (float) $this->business_amount,
            'client_percent' => (int) $this->client_percent,
            'business_percent' => (int) $this->business_percent,
            'my_amount' => (float) ($isClient ? $this->client_amount : $this->business_amount),
            'counterparty' => $this->whenLoaded(
                $isClient ? 'business' : 'client',
                fn () => [
                    'id' => (int) ($isClient ? $this->business_id : $this->client_id),
                    'name' => $isClient ? $this->business?->name : $this->client?->name,
                ]
            ),
            'booking_id' => $this->booking_id !== null ? (int) $this->booking_id : null,
            'target_type' => $this->target_type,
            'target_id' => $this->target_id !== null ? (int) $this->target_id : null,
            'released_at' => optional($this->released_at)->toIso8601String(),
            'refunded_at' => optional($this->refunded_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
