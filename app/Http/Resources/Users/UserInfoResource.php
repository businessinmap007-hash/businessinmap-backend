<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Resources\Json\JsonResource;

class UserInfoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'type' => $this->type,
            'image' => $this->image != "" && file_exists(public_path() . '/' . $this->image) ? $this->image :'/assets/images/avatarempty.png',
            'logo' => $this->logo != "" && file_exists(public_path() . '/' . $this->logo) ? $this->logo :'/assets/images/avatarempty.png',
            'cover' => $this->cover != "" && file_exists(public_path() . '/' . $this->cover) ? $this->cover : '/assets/images/coverbg.png',
            'rate' => $this->averageRating
        ];
    }
}
