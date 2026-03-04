<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => (int)$this->id,
            'status' => $this->status,
            'price' => number_format($this->price, 2, '.', ''),
            'operation' => $this->operation,
            'notes' => $this->notes ?? '',
            'target_id' => $this->target_id,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'target_user' => $this->whenLoaded('targetUser', function () {
                return [
                    'id' => $this->targetUser->id,
                    'name' => $this->targetUser->name,
                    'phone' => $this->targetUser->phone,
                ];
            }),
        ];
    }
}
