<?php

namespace App\Models;

use App\Models\Concerns\HasOwnedImages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripProgramItem extends Model
{
    use HasOwnedImages;

    public const KIND_TEXT = 'text';
    public const KIND_ROOM = 'room';
    public const KIND_IMAGE = 'image';
    public const KINDS = [self::KIND_TEXT, self::KIND_ROOM, self::KIND_IMAGE];

    protected $fillable = ['day_id', 'position', 'kind', 'body', 'allocation_id'];

    public function day(): BelongsTo
    {
        return $this->belongsTo(TripProgramDay::class, 'day_id');
    }

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(BookableAllocation::class, 'allocation_id');
    }
}
