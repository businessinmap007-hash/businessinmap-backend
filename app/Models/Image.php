<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    /** Taken live by the device camera (the only origin allowed for evidence). */
    public const SOURCE_CAMERA = 'camera';

    /** Picked from storage / uploaded. */
    public const SOURCE_UPLOAD = 'upload';

    /** The doctor's own handwritten paper prescription (required when a controlled drug is on it). */
    public const PURPOSE_HANDWRITTEN = 'handwritten_prescription';

    protected $fillable = [
        'image',
        'imageable_id',
        'imageable_type',
        'source',
        'purpose',
    ];

    public function imageable()
    {
        return $this->morphTo();
    }

    public function isCamera(): bool
    {
        return $this->source === self::SOURCE_CAMERA;
    }

    public function scopeCamera(Builder $query): Builder
    {
        return $query->where('source', self::SOURCE_CAMERA);
    }
}
