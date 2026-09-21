<?php

namespace App\Models;

use App\Services\Media\ImageUploadService;
use Illuminate\Database\Eloquent\Model;

/** One photo in a trainer's private library (see the create migration). */
class TrainerPhoto extends Model
{
    /** A generous cap: it is a working library, not an archive. */
    public const MAX_PER_TRAINER = 300;

    protected $fillable = ['trainer_id', 'image', 'caption'];

    protected static function booted(): void
    {
        // Rows AND files die together; mass deletes fire no events, so callers
        // fetch and delete one by one.
        static::deleting(function (self $photo) {
            app(ImageUploadService::class)->delete($photo->image);
        });
    }
}
