<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the stock ledger: a signed quantity in the item's base unit. Never updated or deleted — a mistake is
 * answered by a move that reverses it (`reverses_move_id`).
 */
class Move extends Model
{
    public const UPDATED_AT = null;

    public const RECEIPT = 'receipt';
    public const SALE = 'sale';
    public const SALE_REVERSAL = 'sale_reversal';
    public const WASTE = 'waste';
    public const TRANSFER_OUT = 'transfer_out';
    public const TRANSFER_IN = 'transfer_in';
    public const ADJUSTMENT = 'adjustment';
    public const SUPPLIER_RETURN = 'supplier_return';
    public const OPENING = 'opening';
    public const REVERSAL = 'reversal';

    public const TYPES = [
        self::RECEIPT, self::SALE, self::SALE_REVERSAL, self::WASTE, self::TRANSFER_OUT, self::TRANSFER_IN,
        self::ADJUSTMENT, self::SUPPLIER_RETURN, self::OPENING, self::REVERSAL,
    ];

    protected $table = 'inv_moves';

    protected $guarded = ['id'];

    protected $casts = [
        'qty' => 'float',
        'unit_cost' => 'float',
        'occurred_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }
}
