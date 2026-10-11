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

    /** Ids of the active chairs this account sits at, in one business — in display order. @return list<int> */
    public static function idsOf(int $business, int $userId): array
    {
        return self::query()->where('business_id', $business)->where('staff_user_id', $userId)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The chairs the caller is held to, or null when the whole line is theirs. A staff member who sits at a chair works
     * from that chair alone — «الحلاق بكرسيه فقط»; the owner, and a staff member with no chair, see the whole business.
     *
     * @return list<int>|null
     */
    public static function restrictedTo(\Illuminate\Http\Request $request): ?array
    {
        if (\App\Support\BusinessContext::isOwner($request) || ! $request->user()) {
            return null;
        }

        $own = self::idsOf(\App\Support\BusinessContext::id($request), (int) $request->user()->id);

        return $own === [] ? null : $own;
    }

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
