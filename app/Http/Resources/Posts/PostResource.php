<?php

namespace App\Http\Resources\Posts;

use App\Http\Resources\ImageResource;
use App\Http\Resources\UserResource;
use App\Http\Resources\Users\UserInfoResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
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

//            $this->mergeWhen(request()->headers->get('Authorization') == "",[
            'distance' => getDistanceBetweenPointsNew($request->latitude, $request->longitude, $this->user->latitude, $this->user->longitude, "Km"),
//            ]),
            'like' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                $like = $user->likes()->where('post_id', $this->id)->first();
                if ($like) {
                    return $like->like;
                } else {
                    return null;
                }
            }),

            'isApplied' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                $apply = $user->applies()->where('post_id', $this->id)->first();
                if ($apply) {
                    return true;
                } else {
                    return false;
                }
            }),

            'appliesCount' => $this->applies()->count(),
            'likes' => $this->likes->count(),
            'dislikes' => $this->dislikes->count(),
//            'commentsCountOld' => $this->comments->count(),
            'commentsCount' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();


                if ($user->id == $this->user->id) {
                    return $this->comments->count();
                } else {
                    $privatePosts = $this->comments()->where('status', 'private')
                        ->where(['parent_id' => 0, 'user_id' => $user->id])->get();
                    $comments = $privatePosts->merge($this->comments()->where('status', 'public')->get());
                    return $comments->count();
                }

            }),

            $this->mergeWhen(true, function() {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();


                if ($user){
                    if ($user->id == $this->user->id) {
                        return ['commentsCount' =>  $this->comments->count()];
                    } else {
                        $privatePosts = $this->comments()->where('status', 'private')
                            ->where(['parent_id' => 0, 'user_id' => $user->id])->get();
                        $comments = $privatePosts->merge($this->comments()->where('status', 'public')->get());
                        return ["commentsCount" =>  $comments->count()];
                    }
                }else{
                    return ["commentsCount" =>  $this->comments()->where('status', 'public')->count()];
                }

            }),

            'shareCount' => $this->share_count,
            'user' => new UserInfoResource($this->user),
            'type' => $this->type != "" ? $this->type : "post",
            'expire_at' => $this->expire_at,
            'images' => ImageResource::collection($this->images),
            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
