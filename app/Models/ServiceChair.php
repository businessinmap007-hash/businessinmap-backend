<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A chair of a salon (or a room of a clinic, a table of a workshop): one customer at a time, with the person who
 * serves there. The customer may choose it — «احجز مع أحمد».
 */
class ServiceChair extends Model
{
    protected $fillable = ['business_id', 'name', 'person_name', 'photo', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    /** @return array{id:int,name:?string,person_name:?string,photo:?string} */
    public function payload(): array
    {
        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'person_name' => $this->person_name,
            'photo' => $this->photo,
        ];
    }
}
