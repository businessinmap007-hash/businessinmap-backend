<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One test or exam of an investigation order — its name and (once sent) the centre's price, as they were then. */
class InvestigationOrderItem extends Model
{
    public const KIND_LAB = 'lab';
    public const KIND_RADIOLOGY = 'radiology';

    protected $fillable = ['investigation_order_id', 'option_id', 'kind', 'name', 'price', 'sort_order'];

    protected $casts = ['price' => 'float'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(InvestigationOrder::class, 'investigation_order_id');
    }
}
