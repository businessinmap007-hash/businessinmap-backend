<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A business's answer to a {@see ServiceRequest}: a price, a note, how soon. One live offer per business per request. */
class ServiceRequestOffer extends Model
{
    protected $table = 'service_request_offers';

    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = ['service_request_id', 'business_id', 'price', 'accepts_budget', 'message', 'eta_minutes', 'menu_item_id', 'status'];

    protected $casts = [
        'price' => 'decimal:2',
        'accepts_budget' => 'boolean',
        'eta_minutes' => 'integer',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(User::class, 'business_id');
    }
}
