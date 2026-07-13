<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/** A saved delivery address for the mobile app's address book. */
class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'country_id' => $this->country_id !== null ? (int) $this->country_id : null,
            'governorate_id' => $this->governorate_id !== null ? (int) $this->governorate_id : null,
            'city_id' => $this->city_id !== null ? (int) $this->city_id : null,
            'zip_code' => $this->zip_code,
            'address_line' => $this->address_line,
            'lat' => $this->lat !== null ? (float) $this->lat : null,
            'lng' => $this->lng !== null ? (float) $this->lng : null,
            'is_primary' => (bool) $this->is_primary,
        ];
    }
}
