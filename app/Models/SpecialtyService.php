<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing a specialty clinic does — a dental filling, a filler injection, a laser session. `specialty` says whose list
 * it is, `kind` the section inside it. `owner_id` NULL is the platform's list a clinic picks from; a clinic's own entry
 * (`owner_id` set) is seen and offered by that clinic alone — see the create migration.
 */
class SpecialtyService extends Model
{
    protected $table = 'specialty_services';

    /**
     * specialty => its kinds, in the order a patient reads them. A kind belongs to exactly one specialty, so a kind alone
     * names its list.
     */
    public const KINDS = [
        'dental' => [
            'checkup', 'preventive', 'restorative', 'endodontic', 'periodontal',
            'surgical', 'prosthetic', 'orthodontic', 'cosmetic', 'pediatric',
        ],
        'skin' => [
            'injectables', 'face_lifting', 'laser', 'scars', 'peels', 'skin_disease', 'hair', 'body',
        ],
    ];

    protected $fillable = ['specialty', 'kind', 'name_ar', 'name_en', 'owner_id', 'sort_order'];

    /** @return list<string> every kind of every specialty */
    public static function allKinds(): array
    {
        return array_merge(...array_values(self::KINDS));
    }

    /** @param  list<string>  $specialties
     *  @return list<string> the kinds of these specialties, in reading order */
    public static function kindsOf(array $specialties): array
    {
        $out = [];

        foreach (self::KINDS as $specialty => $kinds) {
            if (in_array($specialty, $specialties, true)) {
                $out = array_merge($out, $kinds);
            }
        }

        return $out;
    }

    public static function specialtyOfKind(string $kind): ?string
    {
        foreach (self::KINDS as $specialty => $kinds) {
            if (in_array($kind, $kinds, true)) {
                return $specialty;
            }
        }

        return null;
    }

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
