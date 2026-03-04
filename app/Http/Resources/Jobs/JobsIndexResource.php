<?php

namespace App\Http\Resources\Jobs;

use App\Http\Resources\UserBasicResource;
use Illuminate\Http\Resources\Json\JsonResource;

class JobsIndexResource extends JsonResource
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
            'body' => $this->body,
            'shareCount' => $this->share_count,
            'user' => new UserBasicResource($this->user)
        ];
    }
}
