<?php

namespace App\Http\Resources;

use App\Http\Resources\Options\OptionResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
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
            $this->mergeWhen($this->parent_id !="", [
                'options' => OptionResource::collection($this->options),
            ]),

            $this->mergeWhen($this->parent_id == 0, [
                'image' => $this->image != "" && file_exists(public_path() . '/' . $this->image) ? $this->image :'/assets/images/avatarempty.png',
                'perMonth' => $this->per_month,
                'perYear' => $this->per_year,
            ]),


            'isFollowed' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                return in_array($this->id, $user->categoryFollows->pluck('id')->toArray());
            }),

            'isTargeted' => $this->when(request()->headers->get('Authorization') != "", function () {
                $token = str_replace('Bearer ', '', request()->headers->get('Authorization'));
                $user = User::whereApiToken($token)->first();
                return in_array($this->id, $user->categoryTargets->pluck('id')->toArray());
            }),

        ];
    }
}
