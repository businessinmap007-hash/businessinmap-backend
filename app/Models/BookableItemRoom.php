<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One numbered room behind a room type (a `BookableItem`). Internal to the hotel: no customer-facing payload carries it
 * until the stay has started.
 */
class BookableItemRoom extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_MAINTENANCE = 'maintenance';

    protected $table = 'bookable_item_rooms';

    protected $fillable = ['bookable_item_id', 'business_id', 'number', 'status', 'notes'];

    protected $casts = [
        'bookable_item_id' => 'integer',
        'business_id' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(BookableItem::class, 'bookable_item_id');
    }
}
