<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One surgery, endoscopy or treatment procedure. `owner_id` NULL is the platform's list a hospital picks from; a hospital's
 * own entry (`owner_id` set) is seen and offered by that hospital alone — see the create migration.
 */
class MedicalProcedure extends Model
{
    public const KIND_SURGERY = 'surgery';
    public const KIND_ENDOSCOPY = 'endoscopy';
    public const KIND_PROCEDURE = 'procedure';

    public const KINDS = [self::KIND_SURGERY, self::KIND_ENDOSCOPY, self::KIND_PROCEDURE];

    protected $fillable = ['kind', 'name_ar', 'name_en', 'owner_id', 'sort_order'];

    /** The platform's list plus this hospital's own entries — never another hospital's. */
    public function scopeVisibleTo(Builder $q, int $hospitalId): Builder
    {
        return $q->where(fn ($w) => $w->whereNull('owner_id')->orWhere('owner_id', $hospitalId));
    }

    public function label(): string
    {
        $ar = trim((string) $this->name_ar);
        $en = trim((string) $this->name_en);
        $primary = app()->getLocale() === 'en' ? $en : $ar;

        return $primary !== '' ? $primary : ($ar !== '' ? $ar : $en);
    }
}
