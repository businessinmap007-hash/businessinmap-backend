<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One coordinate of a priced offering: either WHAT is sold or what qualifies it.
 *
 * @see \App\Models\Concerns\HasOfferingOptions
 */
class OfferingOption extends Model
{
    protected $table = 'offering_options';

    public const ROLE_LINE = 'line';
    public const ROLE_MODIFIER = 'modifier';

    protected $fillable = [
        'offering_type',
        'offering_id',
        'option_id',
        'role',
        'sort_order',
    ];

    protected $casts = [
        'offering_id' => 'integer',
        'option_id' => 'integer',
        'sort_order' => 'integer',
    ];

    public function offering(): MorphTo
    {
        return $this->morphTo();
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(Option::class, 'option_id');
    }
}
