<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    public const DRAFT = 'draft';
    public const PENDING_APPROVAL = 'pending_approval';
    public const APPROVED = 'approved';
    public const PARTIALLY_RECEIVED = 'partially_received';
    public const RECEIVED = 'received';
    public const CLOSED = 'closed';
    public const REJECTED = 'rejected';
    public const CANCELLED = 'cancelled';

    /** Statuses a receipt may still be booked against. */
    public const RECEIVABLE = [self::APPROVED, self::PARTIALLY_RECEIVED];

    protected $table = 'inv_purchase_orders';

    protected $guarded = ['id'];

    protected $casts = [
        'total' => 'float',
        'expected_on' => 'date',
        'approved_at' => 'datetime',
        'sent_at' => 'datetime',
        'supplier_confirmed_at' => 'datetime',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class, 'purchase_order_id')->orderBy('id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }
}
