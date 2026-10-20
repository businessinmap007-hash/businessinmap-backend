<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person's standing answer to «may a business show my project / plan results as a post?» — so he is not asked every time. It
 * covers only anonymous, confirmed results; photos of projects are a separate switch.
 */
class ShowcaseConsent extends Model
{
    protected $fillable = ['user_id', 'projects', 'project_photos', 'training', 'nutrition'];

    protected $casts = ['projects' => 'boolean', 'project_photos' => 'boolean', 'training' => 'boolean', 'nutrition' => 'boolean'];

    /** @return array{projects:bool,project_photos:bool,training:bool,nutrition:bool} */
    public static function forUser(int $userId): array
    {
        $row = static::query()->where('user_id', $userId)->first();

        return [
            'projects' => (bool) ($row->projects ?? false),
            'project_photos' => (bool) ($row->project_photos ?? false),
            'training' => (bool) ($row->training ?? false),
            'nutrition' => (bool) ($row->nutrition ?? false),
        ];
    }
}
