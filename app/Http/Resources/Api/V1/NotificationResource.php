<?php

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Posts\PostResource;
use App\Http\Resources\Users\UserInfoResource;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id'        => $this->id,
            'title'     => $this->title,
            'body'      => $this->body,
            'is_read'   => $this->read_at ? true : false,
            'created_at'=> $this->created_at->diffForHumans(),
            'created_by'=> $this->createdBy ? $this->createdBy->name : null,

            'related'   => [
                'id'   => $this->notifiable_id,
                'type' => $this->notifiable_type,
            ]
        ];
    }
}
