<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WalletTopup */
class WalletTopupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'gateway' => $this->gateway,
            'merchant_ref' => $this->merchant_ref,
            'amount' => (float) $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'method' => $this->method,
            'paid_at' => optional($this->paid_at)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
