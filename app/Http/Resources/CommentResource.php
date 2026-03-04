<?php

namespace App\Http\Resources;

use App\Http\Resources\Posts\PostResource;
use App\Http\Resources\Users\UserInfoResource;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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

            'comment' => $this->comment,
            'status' => $this->status,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
//            $this->mergeWhen(auth()->user(),
//                [
//                    'user' => UserResource::make($this->user)
//                ]
//            ),


            'post' => PostResource::make($this->post),

            'user' => UserInfoResource::make($this->user),

            'repliesCount' => $this->children->count()

//            $this->mergeWhen($this->parent_id == 0,
//                [
//                    'children' => $this->children
//                ]
//            ),
//
//            $this->mergeWhen($this->parent_id != 0,
//                [
//                    'parent' => new CommentResource($this->parent)
//                ]
//            ),


        ];
    }
}
