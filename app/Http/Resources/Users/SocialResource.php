<?php

namespace App\Http\Resources\Users;

use Illuminate\Http\Resources\Json\JsonResource;

class SocialResource extends JsonResource
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
           'facebook' => $this->facebook,
           'twitter' => $this->twitter,
           'instagram' => $this->instagram,
           'youtube' => $this->youtube,
           'linkedin' => $this->linkedin,
       ];
    }
}
