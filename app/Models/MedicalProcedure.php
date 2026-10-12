<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One operation, endoscopy or treatment procedure, filed under its specialty (`kind`). `owner_id` NULL is the platform's list a hospital picks from; a hospital's
 * own entry (`owner_id` set) is seen and offered by that hospital alone — see the create migration.
 */
class MedicalProcedure extends Model
{
    public const KIND_SURGERY = 'surgery';
    public const KIND_ENDOSCOPY = 'endoscopy';
    public const KIND_PROCEDURE = 'procedure';

    /**
     * In the order a patient reads them: the surgical specialties (`surgery` is the general one), then the endoscopies
     * and the treatment procedures. A hospital picks what it performs from each and may add its own under any.
     */
    public const KINDS = [
        self::KIND_SURGERY, 'orthopedic', 'obstetrics', 'urology', 'ent', 'ophthalmology', 'cardiac', 'neuro',
        'plastic', 'pediatric', 'oncology', 'bariatric', 'maxillofacial', self::KIND_ENDOSCOPY, self::KIND_PROCEDURE,
    ];

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
