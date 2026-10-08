<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A doctor listed under one department of a hospital — see the create migration. A doctor with an account is `pending`
 * until that doctor accepts; a doctor with no account is listed as text and is `active` at once.
 */
class HospitalDoctor extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';

    /** Hospitals and medical centres carry departments; a single clinic does not. */
    public const HOSPITAL_CHILDREN = [513, 515];

    /** The group whose options are the departments. */
    public const DEPARTMENT_GROUP = 'تخصصات طبية';

    protected $fillable = ['hospital_id', 'option_id', 'user_id', 'name', 'title', 'status', 'sort_order'];

    public function hospital(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hospital_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }

    public static function isHospital(?User $user): bool
    {
        return $user !== null && in_array((int) ($user->category_child_id ?? 0), self::HOSPITAL_CHILDREN, true);
    }

    /** The name a patient reads: the account's own name and title, or the text the hospital typed. */
    public function shownName(): string
    {
        if ($this->doctor) {
            return $this->doctor->displayName();
        }

        return trim(trim((string) $this->title) . ' ' . trim((string) $this->name));
    }
}
