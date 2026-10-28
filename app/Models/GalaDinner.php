<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * «العشاء الإلزامي»: a night the hotel names whose dinner every guest staying that night pays — a price per adult, a child a percentage
 * of it, a child under an age free. Added to the stay by GalaDinners, whoever sells the room.
 */
class GalaDinner extends Model
{
    protected $fillable = ['business_id', 'date', 'name', 'adult_price', 'child_percent', 'child_free_under'];

    protected $casts = [
        'business_id' => 'integer',
        'date' => 'date:Y-m-d',
        'adult_price' => 'decimal:2',
        'child_percent' => 'decimal:2',
        'child_free_under' => 'integer',
    ];

    /** @return array<string,mixed> */
    public function payload(): array
    {
        return [
            'id' => (int) $this->id,
            'date' => $this->date->toDateString(),
            'name' => (string) $this->name,
            'adult_price' => (float) $this->adult_price,
            'child_percent' => (float) $this->child_percent,
            'child_free_under' => (int) $this->child_free_under,
        ];
    }
}
