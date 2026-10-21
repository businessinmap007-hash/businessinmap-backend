<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A trip a tour company wrote as days and items. See the create_trip_programs migration and
 * {@see \App\Services\TripPrograms\TripProgramService}.
 */
class TripProgram extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    protected $fillable = ['business_id', 'title', 'summary', 'status', 'published_at'];

    protected $casts = ['published_at' => 'datetime'];

    public function days(): HasMany
    {
        return $this->hasMany(TripProgramDay::class, 'program_id')->orderBy('position')->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', self::STATUS_PUBLISHED);
    }

    /** Days own items and items own photos; a mass delete would orphan both, so go one by one. */
    protected static function booted(): void
    {
        static::deleting(function (TripProgram $program) {
            $program->days()->get()->each->delete();
        });
    }
}
