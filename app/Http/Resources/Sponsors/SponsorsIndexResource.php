<?php

namespace App\Http\Resources\Sponsors;

use App\Http\Resources\UserBasicResource;
use App\Http\Resources\UserResource;
use Illuminate\Http\Resources\Json\JsonResource;

class SponsorsIndexResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'type' => $this->type,
            $this->mergeWhen($this->type == 'paid',
                [
                    'expire_at' => $this->expire_at,
                    'price' => $this->price,
                ]
            ),
            'isStopped' => $this->activated_at != null ? true : false,
            'user' => UserBasicResource::make($this->user)
        ];
    }
}
