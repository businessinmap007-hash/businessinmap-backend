<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/** One wallet ledger entry for the mobile app's transaction history. */
class WalletTransactionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'direction' => (string) $this->direction,
            'type' => (string) $this->type,
            'amount' => (float) $this->amount,
            'balance_before' => (float) $this->balance_before,
            'balance_after' => (float) $this->balance_after,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id !== null ? (int) $this->reference_id : null,
            'note' => $this->note,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
