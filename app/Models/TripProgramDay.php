<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TripProgramDay extends Model
{
    protected $fillable = ['program_id', 'position', 'title'];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TripProgram::class, 'program_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TripProgramItem::class, 'day_id')->orderBy('position')->orderBy('id');
    }

    protected static function booted(): void
    {
        static::deleting(function (TripProgramDay $day) {
            $day->items()->get()->each->delete();
        });
    }
}
