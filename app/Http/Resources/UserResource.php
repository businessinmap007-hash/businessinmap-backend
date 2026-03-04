<?php

namespace App\Http\Resources;

use App\Http\Resources\Albums\AlbumResource;
use App\Http\Resources\Posts\PostResource;
use App\Http\Resources\Users\SocialResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'code' => $this->code,
            'type' => $this->type,
            'subCategory' => CategoryResource::make($this->category),
            'parentCategory' => $this->when($this->category && $this->category->parent != null, function () {
                return CategoryResource::make($this->category->parent);

            }),
            'options' => $this->options->pluck('id'),
            'about' => $this->about,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'logo' => $this->logo != "" && file_exists(public_path() . '/' . $this->logo) ? $this->logo :'/assets/images/avatarempty.png',
            'cover' => $this->cover != "" && file_exists(public_path() . '/' . $this->cover) ? $this->cover : '/assets/images/coverbg.png',
            'image' => $this->image,
            'rate' => $this->averageRating,
            'ratingCount' => $this->ratings->count(),
            'socials' => SocialResource::make($this->social),
            'country' => $this->city != null ? CityResource::make($this->city->parent) : "",

            'city' => CityResource::make($this->city),

            'albums' => AlbumResource::collection($this->albums),
            'distance' => getDistanceBetweenPointsNew($request->latitude, $request->longitude, $this->latitude, $this->longitude, "Km"),

            'isFollowed' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                return in_array($this->id, $user->followers->pluck('id')->toArray());

            }),
//
            'isTargeted' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                return in_array($this->id, $user->targets->pluck('id')->toArray());
            }),

            'isSubscription' => ( $this->type == "client") ? "2027-12-31 00:00:00": ($this->subscriptions->where('is_active', 1)->where('finished_at', '>=', Carbon::now())->first() ? $this->subscriptions->where('is_active', 1)->where('finished_at', '>=', Carbon::now())->first()->finished_at : null)
        ];
    }
}
