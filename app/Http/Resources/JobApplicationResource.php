<?php

namespace App\Http\Resources;

use App\Http\Resources\Posts\PostResource;
use Illuminate\Http\Resources\Json\JsonResource;

class JobApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user' => new UserBasicResource($this->user),
            'job' => new PostResource($this->post),
            'approved_at' => $this->approved_at,
            'created_at' => $this->created_at->format('Y-m-d H:i:s')
        ];
//        return parent::toArray($request);
    }
}
