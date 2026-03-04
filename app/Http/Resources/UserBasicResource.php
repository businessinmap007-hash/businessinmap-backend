<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserBasicResource extends JsonResource
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
            'profileCode' => $this->code,
            'logo' => $this->logo != "" && file_exists(public_path() . '/' . $this->logo) ? $this->logo :'/assets/images/avatarempty.png',
            'rate' => $this->averageRating,
            'distance' => getDistanceBetweenPointsNew($request->latitude, $request->longitude, $this->latitude, $this->longitude, "Km"),


        ];
    }
}
