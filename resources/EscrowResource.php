<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EscrowResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'total'     => $this->amount,
            'status'    => $this->status,
            'order_id'  => $this->order_id,

            'client' => [
                'id'   => $this->fromUser->id,
                'name' => $this->fromUser->name,
                'type' => $this->fromUser->account_type
            ],

            'business' => [
                'id'   => $this->toUser->id,
                'name' => $this->toUser->name,
                'type' => $this->toUser->account_type
            ],

            'created_at' => $this->created_at->toDateTimeString(),
        ];
    }
}
