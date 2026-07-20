<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class DisputeSettlementResource extends JsonResource
{
    public function toArray($request): array
    {
        $viewerId = (int) ($request->user()->id ?? 0);

        return [
            'id' => (int) $this->id,
            'status' => $this->status,
            'amount' => (float) $this->amount,
            'method' => $this->method,
            'note' => $this->note,

            // Who hands money to whom. The payee is the only account that may
            // confirm arrival, so the app needs both sides named.
            'payer_side' => $this->payer_side,
            'payee_side' => $this->payeeSide(),

            'proposed_by' => [
                'user_id' => (int) $this->proposed_by_user_id,
                'role' => $this->proposed_by_role,
                'is_me' => (int) $this->proposed_by_user_id === $viewerId,
            ],

            'accepted_at' => optional($this->accepted_at)->toIso8601String(),
            'received_at' => optional($this->received_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
