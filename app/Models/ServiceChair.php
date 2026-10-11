<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A chair of a salon (or a room of a clinic, a table of a workshop): one customer at a time, with the person who
 * serves there. The customer may choose it — «احجز مع أحمد».
 */
class ServiceChair extends Model
{
    protected $fillable = ['business_id', 'name', 'person_name', 'staff_user_id', 'photo', 'sort_order', 'is_active'];

    protected $casts = ['is_active' => 'boolean', 'sort_order' => 'integer'];

    /** The staff member at this chair, when the person is an account of the business. */
    public function staff(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * What a customer (and the business) reads: the person's name and photo — the ones written on the chair, else the
     * staff account's own.
     *
     * @return array{id:int,name:?string,person_name:?string,photo:?string,staff_user_id:?int}
     */
    public function payload(): array
    {
        $account = $this->staff_user_id ? ($this->relationLoaded('staff') ? $this->staff : $this->staff()->first(['id', 'name', 'logo'])) : null;

        $own = trim((string) $this->person_name);

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'person_name' => $own !== '' ? $own : ($account?->name),
            'photo' => $this->photo ?: ($account?->logo),
            'staff_user_id' => $this->staff_user_id ? (int) $this->staff_user_id : null,
        ];
    }
}
