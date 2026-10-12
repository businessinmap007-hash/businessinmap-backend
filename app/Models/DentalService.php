<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing a dental clinic does. `owner_id` NULL is the platform's list a clinic picks from; a clinic's own entry
 * (`owner_id` set) is seen and offered by that clinic alone — see the create migration.
 */
class DentalService extends Model
{
    /** In the order a patient reads them. */
    public const KINDS = [
        'checkup', 'preventive', 'restorative', 'endodontic', 'periodontal',
        'surgical', 'prosthetic', 'orthodontic', 'cosmetic', 'pediatric',
    ];

    protected $fillable = ['kind', 'name_ar', 'name_en', 'owner_id', 'sort_order'];

    /** The platform's list plus this clinic's own entries — never another clinic's. */
    public function scopeVisibleTo(Builder $q, int $clinicId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $clinicId));
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }
}
